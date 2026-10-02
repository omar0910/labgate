<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Horario;
use App\Models\Asistencia;
use App\Models\CentroComputo;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Incidencia;
use App\Models\Equipo; // <-- IMPORTANTE: Agregar esto
use App\Models\AsistenciaProfesor;
use Illuminate\Support\Facades\Mail;
use App\Services\AvisosCorreo;
use Illuminate\Support\Facades\Log;
use App\Models\Semestre;
use App\Models\Materia;
use App\Support\EquipoDelLaboratorio;
use App\Support\ProgresoDelAlumno;
use Illuminate\Support\Str;

class AlumnoDashboardController extends Controller
{
    /**
     * Muestra el dashboard principal del alumno.
     */
    public function index(Request $request)
    {
        if (Auth::user()->rol != 'Alumno') {
            abort(403, 'Acceso no autorizado.');
        }

        // --- ZONA HORARIA BLINDADA ---
        $zonaHoraria = 'America/Hermosillo';

        // --- Lógica de Fecha ---
        // Sólo una fecha real AAAA-MM-DD. Cualquier otra cosa escrita a mano en la
        // dirección (?fecha=abc, 2026-13-45) mostraba un error 500; ahora, hoy.
        $fechaSeleccionada = $request->input('fecha');
        $fecha = is_string($fechaSeleccionada)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaSeleccionada, $partes)
            && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])
            ? Carbon::parse($fechaSeleccionada, $zonaHoraria)
            : Carbon::today($zonaHoraria);

        /** @var \App\Models\User $alumno */
        $alumno = Auth::user();
        $gruposDelAlumno_ids = $alumno->grupos()->pluck('grupos.id');

        // 1. El semestre activo manda: si la fecha consultada cae fuera del periodo
        //    de clases, no hay agenda que mostrar (mismo criterio que los demás roles).
        $semestreActivo = Semestre::activo();
        $fueraDePeriodo = !$semestreActivo || !$semestreActivo->contieneFecha($fecha);

        // 2. Buscamos los horarios de clases para este día, solo del semestre activo.
        //    Un alumno puede seguir inscrito en grupos de semestres anteriores, y esas
        //    clases no deben aparecer ni permitir registrar entrada.
        $horariosHoy = $fueraDePeriodo
            ? collect()
            : Horario::with(['materia', 'centroComputo', 'user'])
                ->whereIn('grupo_id', $gruposDelAlumno_ids)
                ->where('semestre_id', $semestreActivo->id)
                ->queTocanEl($fecha)
                ->orderBy('hora_inicio')
                ->get();

        // 2. Buscamos las asistencias COMPLETAS del día de la clase.
        // Por 'fecha' y no por 'fecha_hora_registro': si el profesor pasó lista otro
        // día, el alumno no vería su propia asistencia.
        $asistenciasMap = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Clase')
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('horario_id');

        // 3. Buscamos sesión de uso libre 
        $sesionUsoLibre = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha', $fecha)
            ->whereNull('fecha_hora_salida')
            ->latest()
            ->first();

        // --- NUEVO: Obtener lista de laboratorios para Uso Libre ---
        $laboratoriosUsoLibre = CentroComputo::where('permite_uso_libre', true)->get();

        // --- LÓGICA DE ESTADO ---
        $now = Carbon::now($zonaHoraria);
        $isToday = $fecha->isToday();

        // Para saber qué pasó en las clases en las que no tiene registro:
        $idsDelDia = $horariosHoy->pluck('id');

        // - si el profesor faltó o justificó, la clase no se dio;
        $profesorDelDia = AsistenciaProfesor::whereIn('horario_id', $idsDelDia)
            ->whereDate('fecha', $fecha)
            ->pluck('estado', 'horario_id');

        // - si otros alumnos sí quedaron registrados, la clase se dio y él no vino;
        $clasesConRegistros = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $idsDelDia)
            ->whereDate('fecha', $fecha)
            ->distinct()
            ->pluck('horario_id')
            ->flip();

        // - y nada cuenta antes de que lo inscribieran al grupo (si se sabe cuándo).
        $inscritoDesde = $alumno->grupos()->get()->mapWithKeys(fn($g) => [
            $g->id => $g->pivot->created_at ? Carbon::parse($g->pivot->created_at)->toDateString() : null,
        ]);

        foreach ($horariosHoy as $horario) {
            $registro = $asistenciasMap->get($horario->id);
            $horario->mi_asistencia = $registro;
            $estadoProfesor = $profesorDelDia[$horario->id] ?? null;

            if ($registro) {
                $horario->estado_asistencia = ($registro->estado == 'presente') ? 'registrada' : $registro->estado;

                // El pase de lista lo marcó presente sin PC y la clase sigue abierta:
                // se le pide su PC en lugar de darlo por registrado.
                if ($isToday && $registro->estado == 'presente' && ! $registro->numero_maquina && ! $registro->equipo_personal) {
                    $horaInicio = Carbon::parse($horario->hora_inicio, $zonaHoraria);
                    $horaFin = Carbon::parse($horario->hora_fin, $zonaHoraria);

                    if ($now->between($horaInicio->copy()->subMinutes(15), $horaFin)) {
                        $horario->estado_asistencia = 'sin_pc';
                    }
                }
            } elseif (in_array($estadoProfesor, ['falta', 'justificado'], true)) {
                // El profesor no dio la clase: no es falta del alumno
                $horario->estado_asistencia = 'no_impartida';
                $horario->profesor_justifico = $estadoProfesor === 'justificado';
            } elseif ($fecha->isPast() && !$isToday) {
                // Antes era "Falta" siempre. Sólo lo es si se sabe que la clase se
                // dio y se tomó asistencia (otros sí quedaron registrados) y ya
                // estaba inscrito; si no, no se sabe (App\Support\FaltasSinRegistro).
                $desde = $inscritoDesde[$horario->grupo_id] ?? null;
                $horario->estado_asistencia = isset($clasesConRegistros[$horario->id]) && (! $desde || $fecha->toDateString() >= $desde)
                    ? 'falta'
                    : 'sin_registro';
            } elseif (!$isToday) {
                $horario->estado_asistencia = 'futuro';
            } else {
                // Parseamos las horas usando la zona horaria correcta
                $horaInicio = Carbon::parse($horario->hora_inicio, $zonaHoraria);
                $horaFin = Carbon::parse($horario->hora_fin, $zonaHoraria);
                $ventanaInicio = $horaInicio->copy()->subMinutes(15);

                if ($now->between($ventanaInicio, $horaFin)) {
                    $horario->estado_asistencia = 'activa';
                } elseif ($now->isBefore($ventanaInicio)) {
                    $horario->estado_asistencia = 'temprano';
                    $horario->ventana_inicio_str = $ventanaInicio->format('h:i A');
                } else {
                    $horario->estado_asistencia = 'tarde';
                    $horario->ventana_fin_str = $horaFin->format('h:i A');
                }
            }
        }

        return view('alumno.dashboard', [
            'horariosHoy' => $horariosHoy,
            'alumno' => $alumno,
            'sesionUsoLibre' => $sesionUsoLibre,
            'fechaMostrada' => $fecha,
            'laboratoriosUsoLibre' => $laboratoriosUsoLibre,
            'semestreActivo' => $semestreActivo,
            'fueraDePeriodo' => $fueraDePeriodo,
            // Si entró desde una PC del laboratorio, cuál es (el número se pone solo)
            'equipoDetectado' => EquipoDelLaboratorio::detectado($request),
            // Desde una laptop o una PC sin script, sólo los que aceptan registro a mano
            'laboratoriosUsoLibreManual' => $laboratoriosUsoLibre->reject(fn($l) => $l->uso_libre_solo_en_sus_pcs)->values(),
        ]);
    }

    public function historial(Request $request)
    {
        /** @var \App\Models\User $alumno */
        $alumno = Auth::user();

        // Los filtros son valores sueltos: un arreglo escrito a mano en la dirección
        // (?estado[]=x) tiraba la pantalla con un error 500. Se ignora.
        foreach (['semestre_id', 'materia_id', 'tipo', 'estado', 'fecha_inicio', 'fecha_fin'] as $campo) {
            if (is_array($request->query($campo))) {
                $request->query->remove($campo);
            }
        }

        // --- LÓGICA DE SEMESTRES ---
        // Se llama directo al modelo, asegúrate de tener "use App\Models\Semestre;" arriba
        $semestres = \App\Models\Semestre::orderBy('id', 'desc')->get();
        $semestreActivo = $semestres->firstWhere('es_activo', 1);
        $semestreId = $this->semestreElegido($request, $semestres, $semestreActivo);

        // A. OBTENER LAS MATERIAS DEL ALUMNO (Para el filtro desplegable)
        $gruposIds = $alumno->grupos()->pluck('grupos.id');

        $materiasFilterQuery = Horario::whereIn('grupo_id', $gruposIds)->with('materia');

        // Filtramos para que en el select solo salgan materias del semestre elegido
        if ($semestreId) {
            $materiasFilterQuery->where('semestre_id', $semestreId);
        }

        $materiasFilter = $materiasFilterQuery->get()->pluck('materia')->unique('id');

        // B. INICIAR LA CONSULTA DEL HISTORIAL
        $query = Asistencia::where('user_id', $alumno->id)
            ->with(['horario.materia', 'horario.user', 'centroComputo']);

        // --- FILTRO GLOBAL: SEMESTRE SELECCIONADO ---
        if ($semestreId) {
            $query->where(function ($q) use ($semestreId) {
                // Filtra las clases que pertenecen al semestre
                $q->whereHas('horario', function ($h) use ($semestreId) {
                    $h->where('semestre_id', $semestreId);
                })
                    // Permite mostrar el Uso Libre en el historial de cualquier semestre
                    ->orWhere('tipo', 'Uso Libre');
            });
        }

        // --- APLICAR FILTROS DEL USUARIO ---
        if ($request->filled('materia_id')) {
            $query->whereHas('horario', function ($q) use ($request) {
                $q->where('materia_id', $request->materia_id);
            });
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        $asistencias = $query->orderBy('fecha', 'desc')
            ->orderBy('fecha_hora_registro', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Agregamos las variables de semestre a la vista
        return view('alumno.historial', compact('asistencias', 'materiasFilter', 'semestres', 'semestreId'));
    }

    public function marcarAsistencia(Request $request)
    {
        $zonaHoraria = 'America/Hermosillo';

        // Fuera del periodo de clases no se registra nada, aunque llegue la petición
        // directamente: el horario podría ser de un semestre ya cerrado.
        $semestreActivo = Semestre::activo();
        if (!$semestreActivo || !$semestreActivo->contieneFecha(Carbon::today($zonaHoraria))) {
            return redirect()->back()->with('error', 'No se puede registrar: la fecha de hoy está fuera del periodo de clases.');
        }

        // 1. OBTENER DATOS DEL LABORATORIO PRIMERO (sólo con un número: una lista
        //    armada a mano en la petición daba error 500)
        $horario = is_numeric($request->horario_id) ? Horario::with('centroComputo')->find($request->horario_id) : null;

        if (!$horario) {
            return redirect()->back()->with('error', 'Horario no válido.');
        }

        if ($horario->semestre_id != $semestreActivo->id) {
            return redirect()->back()->with('error', 'Esa clase pertenece a un semestre que ya terminó.');
        }

        $capacidadMaxima = $horario->centroComputo->capacidad ?? 40;

        $request->validate([
            'horario_id' => 'required|exists:horarios,id',
        ]);

        /** @var \App\Models\User $alumno */
        $alumno = Auth::user();
        $hoy = Carbon::today($zonaHoraria);
        $now = Carbon::now($zonaHoraria);

        // La clase tiene que ser de SU grupo y tocar HOY. Antes sólo se revisaba la
        // hora: enviando otro horario_id, un alumno podía registrarse en la clase de
        // otro grupo, o un lunes en una clase de los miércoles a la misma hora.
        if (! $alumno->grupos()->where('grupos.id', $horario->grupo_id)->exists()) {
            return redirect()->back()->with('error', 'No estás inscrito en el grupo de esa clase.');
        }

        if (! Horario::whereKey($horario->id)->queTocanEl($hoy)->exists()) {
            return redirect()->back()->with('error', 'Esa clase no se imparte hoy.');
        }

        // 3. Comprobar si ya existe un registro de esta clase HOY (por el día de la
        //    clase, 'fecha', igual que el resto del sistema). Va antes de revisar la
        //    PC: si no, a quien ya se registró se le decía que su propia PC estaba
        //    "ocupada por otro alumno".
        $existente = Asistencia::where('user_id', $alumno->id)
            ->where('horario_id', $request->horario_id)
            ->whereDate('fecha', $hoy->toDateString())
            ->where('tipo', 'Clase')
            ->first();

        // Si el profesor (o el encargado) pasó lista antes de que el alumno se
        // registrara, la lista lo dejó "presente" SIN número de PC: la lista marca
        // presente por omisión. Antes aquí se le rechazaba con "ya te registraste" y
        // su PC nunca quedaba anotada (y con el script de bloqueo, esa PC seguía
        // bloqueada). Ahora puede completar ese mismo registro con su PC.
        $completarLista = $existente
            && $existente->estado === 'presente'
            && ! $existente->numero_maquina
            && ! $existente->equipo_personal;

        if ($existente && in_array($existente->estado, ['falta', 'justificado'], true)) {
            return redirect()->route('alumno.dashboard')
                ->with('error', 'Tu profesor ya pasó lista y te marcó '
                    . ($existente->estado === 'falta' ? 'falta' : 'falta justificada')
                    . '. Si estás en clase, pídele que lo corrija.');
        }

        if ($existente && ! $completarLista) {
            return redirect()->route('alumno.dashboard')
                ->with('error', 'Ya has registrado tu asistencia para esta clase.');
        }

        // =========================================================
        //  ¿CON QUÉ EQUIPO?
        //  - Desde una PC del laboratorio (el script la identifica): esa, sin
        //    poder cambiarla ni marcar "equipo personal".
        //  - Desde una laptop: su equipo personal, sin número de PC.
        //  - Si no se detecta nada: el número que escriba, como siempre.
        // =========================================================
        $detectado = EquipoDelLaboratorio::detectado($request);
        $personal = false;

        if ($detectado) {
            // En otra sala no: esa PC se quedaría bloqueada y se liberaría la del
            // mismo número en el laboratorio de la clase.
            if ($detectado['centro'] !== (int) $horario->centro_computo_id) {
                return redirect()->back()->with('error', 'Estás en la PC #' . $detectado['maquina'] . ' de '
                    . $detectado['laboratorio']->nombre_centro . ', pero esta clase es en '
                    . ($horario->centroComputo->nombre_centro ?? 'otro laboratorio')
                    . '. Regístrate desde una computadora de ese laboratorio.');
            }

            $numeroMaquina = $detectado['maquina'];
        } elseif ($request->boolean('equipo_personal')) {
            $numeroMaquina = null;
            $personal = true;
        } else {
            $request->validate([
                'numero_maquina' => 'required|integer|min:1|max:' . $capacidadMaxima,
            ], [
                'numero_maquina.required' => 'Escribe el número de tu PC, o marca "Uso mi equipo personal".',
            ]);

            $numeroMaquina = (int) $request->numero_maquina;
        }

        // Con equipo personal no se ocupa ninguna PC: no hay nada que revisar
        if ($numeroMaquina) {
            // =========================================================
            //  EL ESCUDO DE MANTENIMIENTO FÍSICO
            // =========================================================
            $equipoFisico = Equipo::where('centro_computo_id', $horario->centro_computo_id)
                ->where('numero_maquina', $numeroMaquina)
                ->first();

            if ($equipoFisico && $equipoFisico->estado !== 'disponible') {
                return redirect()->back()->with('error', '⚠️ La PC #' . $numeroMaquina . ' se encuentra fuera de servicio. Por favor, '
                    . ($detectado ? 'avisa al encargado y cámbiate a otra computadora.' : 'elige otra computadora.'));
            }

            // =========================================================
            // BLOQUEO DE CONCURRENCIA
            // =========================================================
            if (Asistencia::estaOcupada($horario->centro_computo_id, $numeroMaquina)) {
                return redirect()->back()
                    ->with('error', '⚠️ La PC #' . $numeroMaquina . ' ya está ocupada por otro alumno. Por favor '
                        . ($detectado ? 'avisa al encargado.' : 'selecciona otra.'));
            }
        }

        // 4. VALIDAR ESTADO DEL PROFESOR
        $reporteProfe = AsistenciaProfesor::where('horario_id', $request->horario_id)
            ->where('fecha', $hoy->toDateString())
            ->first();

        if ($reporteProfe && in_array($reporteProfe->estado, ['falta', 'justificado'])) {
            return redirect()->route('alumno.dashboard')
                ->with('error', 'No puedes registrar asistencia. La clase ha sido marcada como NO IMPARTIDA.');
        }

        // 5. LÓGICA DE VENTANA DE TIEMPO BLINDADA
        $horaInicio = Carbon::parse($horario->hora_inicio, $zonaHoraria);
        $horaFin = Carbon::parse($horario->hora_fin, $zonaHoraria);

        $ventanaInicio = $horaInicio->copy()->subMinutes(15);
        $ventanaFin = $horaFin;

        if (!$now->between($ventanaInicio, $ventanaFin)) {
            return redirect()->route('alumno.dashboard', ['fecha' => $hoy->toDateString()])
                ->with('error', 'Asistencia cerrada. Solo puedes registrarte 15 minutos antes y durante tu clase.');
        }

        // 6a. COMPLETAR EL REGISTRO QUE DEJÓ EL PASE DE LISTA
        //     Se respeta todo lo demás (estado, hora, comentario del profesor); sólo
        //     se anota con qué equipo está. Sin correo: la lista ya le avisó.
        if ($completarLista) {
            $existente->numero_maquina = $numeroMaquina;
            $existente->equipo_personal = $personal;
            $existente->save();

            // Al crearse sin PC no se le sumó el uso a ninguna máquina
            $existente->sumarUsoAlEquipo();

            return redirect()->route('alumno.dashboard', ['fecha' => $hoy->toDateString()])
                ->with('success', $personal
                    ? '¡Listo! Tu asistencia quedó con tu equipo personal.'
                    : '¡Listo! Se anotó la PC #' . $numeroMaquina . ' en tu asistencia.');
        }

        // 6b. CREAR EL REGISTRO
        $asistencia = Asistencia::create([
            'user_id' => $alumno->id,
            'horario_id' => $request->horario_id,
            'numero_maquina' => $numeroMaquina,
            'equipo_personal' => $personal,
            'tipo' => 'Clase',
            'estado' => 'presente',
            'fecha_hora_registro' => $now,
            'fecha' => $hoy->toDateString(),
            'centro_computo_id' => $horario->centro_computo_id,
        ]);

        // Confirmación al alumno. Va en cola y no interrumpe la respuesta.
        AvisosCorreo::asistenciaDeClase($alumno, $asistencia, 'alumno');

        return redirect()->route('alumno.dashboard', ['fecha' => $hoy->toDateString()])
            ->with('success', $personal
                ? '¡Asistencia registrada con tu equipo personal!'
                : '¡Asistencia registrada exitosamente en la PC #' . $numeroMaquina . '!');
    }

    public function registrarUsoLibre(Request $request)
    {
        $zonaHoraria = 'America/Hermosillo';

        // Fuera del periodo de clases no se registra nada, aunque llegue la petición
        // directamente: el horario podría ser de un semestre ya cerrado.
        $semestreActivo = Semestre::activo();
        if (!$semestreActivo || !$semestreActivo->contieneFecha(Carbon::today($zonaHoraria))) {
            return redirect()->back()->with('error', 'No se puede registrar: la fecha de hoy está fuera del periodo de clases.');
        }

        // Desde una PC del laboratorio, el laboratorio y la PC son los de esa
        // computadora, sin importar lo que venga en el formulario.
        $detectado = EquipoDelLaboratorio::detectado($request);
        if ($detectado) {
            $request->merge([
                'centro_computo_id' => $detectado['centro'],
                'numero_maquina'    => $detectado['maquina'],
            ]);
        }

        $request->validate([
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'numero_maquina' => 'required|integer|min:1',
        ]);

        $alumno = Auth::user();
        $hoy = Carbon::today($zonaHoraria)->toDateString();
        $now = Carbon::now($zonaHoraria);

        $labSeleccionado = CentroComputo::where('id', $request->centro_computo_id)
            ->where('permite_uso_libre', true)
            ->first();

        if (!$labSeleccionado) {
            return redirect()->back()
                ->with('error', $detectado
                    ? 'Estás en una PC de ' . $detectado['laboratorio']->nombre_centro . ', que no tiene Uso Libre.'
                    : 'El laboratorio seleccionado no está habilitado para Uso Libre.');
        }

        // Laboratorios cuyo uso libre sólo se registra desde sus computadoras: el uso
        // libre es para usar las PCs del centro, no para quien trae su laptop.
        if ($labSeleccionado->uso_libre_solo_en_sus_pcs && ! $detectado) {
            return redirect()->back()
                ->with('error', 'El Uso Libre de ' . $labSeleccionado->nombre_centro
                    . ' se registra desde una de sus computadoras, no desde un equipo personal.');
        }

        if ($request->numero_maquina > $labSeleccionado->capacidad) {
            return redirect()->back()
                ->with('error', "La máquina #{$request->numero_maquina} no existe. {$labSeleccionado->nombre_centro} solo tiene {$labSeleccionado->capacidad} equipos.");
        }

        $equipoFisico = Equipo::where('centro_computo_id', $request->centro_computo_id)
            ->where('numero_maquina', $request->numero_maquina)
            ->first();

        if ($equipoFisico && $equipoFisico->estado !== 'disponible') {
            return redirect()->back()->with('error', '⚠️ La PC #' . $request->numero_maquina . ' se encuentra fuera de servicio. Por favor, elige otra computadora.');
        }

        $ocupada = Asistencia::estaOcupada($request->centro_computo_id, $request->numero_maquina);

        if ($ocupada) {
            return redirect()->back()
                ->with('error', '⚠️ La PC #' . $request->numero_maquina . ' ya está en uso. Intenta con otra.');
        }

        $sesionActiva = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha_hora_registro', $hoy)
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($sesionActiva) {
            return redirect()->route('alumno.dashboard')
                ->with('error', 'Ya tienes una sesión activa. Debes terminarla antes de iniciar otra.');
        }

        $sesion = Asistencia::create([
            'user_id' => $alumno->id,
            'horario_id' => null,
            'numero_maquina' => $request->numero_maquina,
            'tipo' => 'Uso Libre',
            'estado' => 'presente',
            'fecha_hora_registro' => $now,
            'fecha' => $hoy,
            'centro_computo_id' => $labSeleccionado->id,
        ]);

        // Aviso con los datos del equipo y el recordatorio de cerrar la sesión.
        AvisosCorreo::entradaUsoLibre($alumno, $sesion);

        return redirect()->route('alumno.dashboard')
            ->with('success', '¡Entrada registrada en ' . $labSeleccionado->nombre_centro . ' - PC #' . $request->numero_maquina . '!');
    }

    public function terminarUsoLibre(Request $request)
    {
        $zonaHoraria = 'America/Hermosillo';
        $alumno = Auth::user();
        $hoy = Carbon::today($zonaHoraria)->toDateString();
        $now = Carbon::now($zonaHoraria);

        $sesion = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha_hora_registro', $hoy)
            ->whereNull('fecha_hora_salida')
            ->first();

        if ($sesion) {
            $sesion->update([
                'fecha_hora_salida' => $now
            ]);

            // Resumen de la visita: entrada, salida, duración y equipo.
            AvisosCorreo::salidaUsoLibre($alumno, $sesion);

            return redirect()->route('alumno.dashboard')
                ->with('success', 'Has terminado tu sesión de uso libre.');
        }

        return redirect()->route('alumno.dashboard')
            ->with('error', 'No se encontró una sesión activa para terminar.');
    }

    public function misMaterias(Request $request)
    {
        /** @var \App\Models\User $alumno */
        $alumno = Auth::user();

        // --- LÓGICA DE SEMESTRES ---
        $semestres = \App\Models\Semestre::orderBy('id', 'desc')->get();
        $semestreActivo = $semestres->firstWhere('es_activo', 1);
        $semestreId = $this->semestreElegido($request, $semestres, $semestreActivo);

        // Cada clase que cuenta: sus registros y las clases que se dieron, en las que
        // otros sí quedaron registrados y él no (antes no se contaban y el porcentaje
        // salía inflado). El detalle de cada materia usa el mismo cálculo, así que la
        // tarjeta y el detalle dicen lo mismo (ver App\Support\ProgresoDelAlumno).
        $clases = ProgresoDelAlumno::clases($alumno, $semestreId ? (int) $semestreId : null);

        $resumen = $clases
            ->groupBy(fn($clase) => $clase['materia_id'] ?? 'sin-materia')
            ->map(function ($clasesMateria) {
                $primera = $clasesMateria->first();

                return ProgresoDelAlumno::resumen($clasesMateria) + [
                    'materia' => $primera['materia'],
                    // Para abrir su detalle (no si la materia ya no existe)
                    'materia_id' => $primera['horario']?->materia ? $primera['materia_id'] : null,
                    // Las últimas clases, de la más antigua a la más reciente
                    'ultimas' => $clasesMateria->take(8)->reverse()->values(),
                ];
            })
            ->sortBy(fn($dato) => Str::lower(Str::ascii($dato['materia'])))
            ->values()
            ->all();

        // Todas sus materias juntas, para el resumen de arriba
        $general = ProgresoDelAlumno::resumen($clases);

        // Agregamos las variables de semestre a la vista
        return view('alumno.materias', compact('resumen', 'general', 'semestres', 'semestreId'));
    }

    /**
     * Detalle de una materia de "Mi Progreso": cada clase que cuenta para su
     * porcentaje (con su PC y la nota del profesor) y las que no se dieron.
     */
    public function detalleMateria(Request $request, Materia $materia)
    {
        /** @var \App\Models\User $alumno */
        $alumno = Auth::user();

        $semestres = Semestre::orderBy('id', 'desc')->get();
        $semestreActivo = $semestres->firstWhere('es_activo', 1);
        $semestreId = $this->semestreElegido($request, $semestres, $semestreActivo);

        $clases = ProgresoDelAlumno::clases($alumno, $semestreId ? (int) $semestreId : null, $materia->id);

        // Sus clases de esta materia en el semestre: grupo, profesor, día y laboratorio
        $horarios = Horario::with(['user', 'centroComputo', 'grupo'])
            ->whereIn('grupo_id', $alumno->grupos()->pluck('grupos.id'))
            ->where('materia_id', $materia->id)
            ->when($semestreId, fn($q) => $q->where('semestre_id', $semestreId))
            ->orderBy('hora_inicio')
            ->get();

        // Una materia que no lleva y en la que no tiene registros no es suya
        if ($clases->isEmpty() && $horarios->isEmpty()) {
            abort(404);
        }

        // Si ya no está en el grupo, al menos las clases de sus registros
        if ($horarios->isEmpty()) {
            $horarios = $clases->pluck('horario')->filter()->unique('id')->values();
            $horarios->each(fn($horario) => $horario->loadMissing(['user', 'centroComputo', 'grupo']));
        }

        // Las que no se dieron no cuentan, pero se muestran para que sepa por qué
        // ese día no aparece como falta
        $bitacora = ProgresoDelAlumno::ordenar($clases->concat(ProgresoDelAlumno::noImpartidas($alumno, $horarios)));

        return view('alumno.materia-detalle', [
            'materia' => $materia,
            'resumen' => ProgresoDelAlumno::resumen($clases),
            'bitacora' => $bitacora,
            'horarios' => $horarios,
            'semestre' => $semestres->firstWhere('id', $semestreId),
            'semestreId' => $semestreId,
        ]);
    }

    public function reportarIncidencia(Request $request)
    {
        $request->validate([
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'numero_maquina' => 'required|integer|min:1',
            'categoria' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:500',
        ]);

        // Una PC que no existe en ese laboratorio (sólo pasa si se altera el
        // formulario) quedaba como falla pendiente para el encargado.
        $laboratorio = CentroComputo::find($request->centro_computo_id);
        if ($laboratorio->capacidad && $request->numero_maquina > $laboratorio->capacidad) {
            return back()->with('error', "La PC #{$request->numero_maquina} no existe. {$laboratorio->nombre_centro} sólo tiene {$laboratorio->capacidad} equipos.");
        }

        Incidencia::create([
            'user_id' => Auth::id(),
            'centro_computo_id' => $request->centro_computo_id,
            'numero_maquina' => $request->numero_maquina,
            'categoria' => $request->categoria,
            'descripcion' => $request->descripcion,
            'estado' => 'pendiente'
        ]);

        return back()->with('success', 'Reporte enviado correctamente. El encargado revisará el equipo.');
    }

    /**
     * El semestre que pidió en el selector, si existe; si no (o si no pidió
     * ninguno), el activo. Antes cualquier valor pasaba tal cual y con uno
     * inválido "Mi Progreso" mezclaba las faltas de todos los semestres.
     */
    protected function semestreElegido(Request $request, $semestres, $semestreActivo)
    {
        $pedido = $request->input('semestre_id');
        $elegido = is_numeric($pedido) ? $semestres->firstWhere('id', (int) $pedido) : null;

        return $elegido->id ?? ($semestreActivo->id ?? null);
    }
}
