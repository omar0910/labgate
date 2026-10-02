<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Importante para DB::raw
use App\Models\Horario;
use App\Models\Asistencia;
use App\Models\User;
use App\Services\AvisosCorreo;
use App\Models\Grupo;
use App\Models\Semestre;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\ListaAlumnosExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Exports\EstadisticasExport;
use App\Support\ClasesDelProfesor;
use App\Support\EstadisticasDeClase;


class ProfesorController extends Controller
{
    /**
     * Dashboard: Muestra las clases de HOY.
     */
    public function index()
    {
        if (Auth::user()->rol != 'Profesor') {
            abort(403, 'Acceso no autorizado.');
        }

        $profesorId = Auth::id();

        // 1. Obtener el día de hoy
        $diasMap = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $hoy = Carbon::now();
        $diaHoy = $diasMap[$hoy->dayOfWeekIso];

        // 2. El semestre activo manda: si hoy cae fuera del periodo de clases,
        //    no hay nada que mostrar (mismo criterio que los Jobs de correo).
        $semestreActivo = Semestre::activo();
        $fueraDePeriodo = !$semestreActivo || !$semestreActivo->contieneFecha($hoy);

        // 3. Buscar clases SOLO de hoy y SOLO del semestre activo, ordenadas por hora
        $clasesHoy = $fueraDePeriodo
            ? collect()
            : Horario::with(['materia', 'grupo', 'centroComputo'])
                ->where('user_id', $profesorId)
                ->where('semestre_id', $semestreActivo->id)
                ->queTocanEl($hoy)
                ->orderBy('hora_inicio')
                ->get();

        // 4. Si hoy no tiene clases, cuál es la próxima (antes sólo decía "¡Día libre!")
        $proximaClase = null;
        if (! $fueraDePeriodo && $clasesHoy->isEmpty()) {
            $proximaClase = $this->proximaClase($profesorId, $semestreActivo, $hoy);
        }

        // 5. Cómo va cada clase de hoy: cuántos alumnos se han registrado y si ya
        //    empezó o terminó, para saber sin abrirla si falta pasar lista.
        $registradosHoy = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $clasesHoy->pluck('id'))
            ->whereDate('fecha', $hoy->toDateString())
            ->select('horario_id', DB::raw('COUNT(*) as total'))
            ->groupBy('horario_id')
            ->pluck('total', 'horario_id');

        $alumnosPorGrupo = Grupo::whereIn('id', $clasesHoy->pluck('grupo_id')->filter())
            ->withCount('alumnos')
            ->pluck('alumnos_count', 'id');

        $horaActual = $hoy->format('H:i:s');
        foreach ($clasesHoy as $clase) {
            $clase->registrados = (int) ($registradosHoy[$clase->id] ?? 0);
            $clase->total_alumnos = (int) ($alumnosPorGrupo[$clase->grupo_id] ?? 0);
            $clase->momento = $horaActual < $clase->hora_inicio ? 'antes' : ($horaActual <= $clase->hora_fin ? 'en_curso' : 'terminada');
        }

        // 6. Clases pasadas del semestre en las que no quedó ninguna asistencia
        $sinLista = $fueraDePeriodo
            ? 0
            : ClasesDelProfesor::pasadas($profesorId, $semestreActivo)
                ->where('estado', 'sin_lista')
                ->where('fecha', '<', $hoy->toDateString())
                ->count();

        return view('profesor.dashboard', [
            'clasesHoy' => $clasesHoy,
            'diaHoy' => $diaHoy,
            'semestreActivo' => $semestreActivo,
            'fueraDePeriodo' => $fueraDePeriodo,
            'proximaClase' => $proximaClase,
            'sinLista' => $sinLista,
        ]);
    }

    /**
     * La siguiente clase del profesor a partir de mañana, dentro del semestre y sin
     * contar los días inhábiles: ['fecha' => Carbon, 'horario' => Horario] o null.
     */
    protected function proximaClase(int $profesorId, Semestre $semestre, Carbon $desde): ?array
    {
        $inhabiles = \App\Models\DiaInhabil::where('semestre_id', $semestre->id)
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
            ->flip();

        $fin = Carbon::parse($semestre->fecha_fin);

        // Tres semanas bastan para encontrarla aunque haya vacaciones cortas
        for ($dia = $desde->copy()->addDay()->startOfDay(), $i = 0; $i < 21 && $dia->lte($fin); $dia->addDay(), $i++) {
            if (isset($inhabiles[$dia->format('Y-m-d')])) {
                continue;
            }

            $horario = Horario::with(['materia', 'centroComputo'])
                ->where('user_id', $profesorId)
                ->where('semestre_id', $semestre->id)
                ->queTocanEl($dia)
                ->orderBy('hora_inicio')
                ->first();

            if ($horario) {
                return ['fecha' => $dia->copy(), 'horario' => $horario];
            }
        }

        return null;
    }

    /**
     * Historial: todas sus clases que ya pasaron, con cómo quedó cada lista.
     *
     * Antes había que escoger una fecha y sólo salían las clases de ese día; para
     * saber qué listas faltaban había que revisar día por día. Escoger un día
     * sigue funcionando (?fecha=AAAA-MM-DD), para ir directo a uno.
     */
    public function historial(Request $request)
    {
        $profesorId = Auth::id();
        $semestres = Semestre::orderBy('id', 'desc')->get();

        // ¿Pidió un día en particular?
        $dia = $request->filled('fecha') ? $this->fechaValida($request->input('fecha')) : null;
        $fechaInvalida = $request->filled('fecha') && ! $dia;

        if ($dia) {
            // El semestre al que pertenece ese día (puede no haber ninguno)
            $semestre = $semestres->first(fn($s) => $s->contieneFecha($dia));
        } else {
            $pedido = $request->input('semestre_id');
            $semestre = (is_numeric($pedido) ? $semestres->firstWhere('id', (int) $pedido) : null)
                ?? $semestres->firstWhere('es_activo', 1);
        }

        $clases = $semestre ? ClasesDelProfesor::pasadas($profesorId, $semestre, $dia) : collect();

        // Para el filtro por clase: cada materia con su grupo
        $opcionesClase = $clases
            ->map(fn($c) => [
                'clave' => $c['horario']->materia_id . '-' . $c['horario']->grupo_id,
                'texto' => ($c['horario']->materia->nombre_materia ?? 'Clase') . ' · ' . ($c['horario']->grupo->nombre_grupo ?? ''),
            ])
            ->unique('clave')
            ->sortBy('texto')
            ->values();

        return view('profesor.historial', [
            'semestres' => $semestres,
            'semestre' => $semestre,
            'dia' => $dia,
            'fechaInvalida' => $fechaInvalida,
            'clases' => $clases,
            // Agrupadas por semana, de la más reciente a la más antigua
            'porSemana' => $clases->groupBy(fn($c) => Carbon::parse($c['fecha'])->startOfWeek()->format('Y-m-d')),
            'conteos' => $clases->countBy('estado'),
            'opcionesClase' => $opcionesClase,
        ]);
    }

    /**
     * Muestra la lista de alumnos para revisar asistencia.
     * Sirve tanto para HOY como para fechas del HISTORIAL.
     */
    // CAMBIO IMPORTANTE: Renombramos el parámetro a $id para coincidir con la ruta estándar
    public function revisarClase(Request $request, $id)
    {
        $profesorId = Auth::id();

        // Validar y obtener la clase
        $horario = Horario::with(['materia', 'grupo.alumnos', 'semestre'])
            ->where('id', $id) // Usamos $id que viene de la ruta
            ->where('user_id', $profesorId)
            ->firstOrFail();

        // Si viene fecha en el request (historial), la usamos. Si no, usamos HOY.
        // Tiene que ser una fecha real, que ya llegó y en la que esta clase se da:
        // antes se podía abrir (y guardar) la lista de un martes con fecha de
        // miércoles, y esa "clase" contaba como dada en las estadísticas.
        $fecha = $request->filled('fecha') ? $this->fechaValida($request->input('fecha')) : Carbon::today()->toDateString();

        if (! $fecha) {
            return redirect()->route('profesor.historial')->with('error', 'La fecha de esa lista no es válida.');
        }

        if ($problema = $this->problemaConLaFecha($horario, $fecha)) {
            return redirect()->route('profesor.historial')->with('error', $problema);
        }

        // Obtenemos los alumnos ordenados por apellidos alfabéticamente
        $alumnos = $horario->grupo->alumnos->sortBy([
            ['apellido_paterno', 'asc'],
            ['apellido_materno', 'asc'],
            ['name', 'asc'],
        ])->values(); // El values() es importante para resetear la numeración 1, 2, 3...

        // Obtenemos las asistencias YA registradas para esa fecha
        $asistencias = Asistencia::where('horario_id', $horario->id)
            ->where('fecha', $fecha)
            ->where('tipo', 'Clase')
            ->get()
            ->keyBy('user_id');

        // Si la clase está registrada como no impartida (el profesor faltó o
        // justificó), se avisa: el alumno no puede registrarse en ella.
        $estadoProfesor = \App\Models\AsistenciaProfesor::where('horario_id', $horario->id)
            ->whereDate('fecha', $fecha)
            ->value('estado');

        return view('profesor.revisar', [
            'horario' => $horario,
            'alumnos' => $alumnos,
            'asistencias' => $asistencias,
            'fecha' => $fecha,
            'estadoProfesor' => $estadoProfesor,
            // Mientras dure, quien no esté marcado todavía puede registrarse
            'claseEnCurso' => $this->claseEnCurso($horario, $fecha),
        ]);
    }

    /** Sólo fechas reales AAAA-MM-DD; cualquier otra cosa, null. */
    protected function fechaValida($valor): ?string
    {
        return is_string($valor)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)
            && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])
            ? $valor
            : null;
    }

    /**
     * Por qué no se puede pasar lista de esta clase en esa fecha, o null si sí se
     * puede: que ya haya llegado, que esté dentro de su semestre y que la clase se
     * dé ese día (su día de la semana, o la fecha de la reserva especial).
     */
    protected function problemaConLaFecha(Horario $horario, string $fecha): ?string
    {
        // La misma revisión que la Bitácora y "Gestión de Clases" (ver Horario)
        return $horario->problemaParaLaFecha($fecha);
    }

    /**
     * Guarda o actualiza la asistencia (Modo Guardado Masivo).
     */
    public function guardarAsistencia(Request $request)
    {
        $request->validate([
            'horario_id'  => 'required|exists:horarios,id',
            'fecha'       => 'required|date_format:Y-m-d|before_or_equal:today',
            'asistencias' => 'required|array', // Ahora recibimos un arreglo con todos los alumnos
            'asistencias.*.estado'     => 'nullable|in:presente,falta,justificado',
            'asistencias.*.comentario' => 'nullable|string|max:1000',
        ], [
            'fecha.before_or_equal' => 'No se puede pasar lista de una fecha que aún no llega.',
        ]);

        // Sólo SUS clases. Antes bastaba con cambiar el horario_id del formulario
        // para guardar (o cambiar) la lista de la clase de otro profesor.
        $horario = Horario::where('user_id', Auth::id())->findOrFail($request->horario_id);

        // Sólo en un día en que esta clase se da y dentro de su semestre: antes se
        // guardaba la lista de un martes con fecha de miércoles (o de otro año).
        if ($problema = $this->problemaConLaFecha($horario, $request->fecha)) {
            return back()->with('error', $problema);
        }

        // Sólo alumnos inscritos en el grupo; se cargan de una vez para no consultar
        // uno por uno al avisar.
        $alumnos = $horario->grupo
            ? $horario->grupo->alumnos()->whereIn('users.id', array_keys($request->asistencias))->get()->keyBy('id')
            : collect();

        // Cuenta sólo los avisos que de verdad salen, para escalonarlos en el tiempo.
        $avisados = 0;

        // Cuántos se guardaron y cuántos se quedaron sin marcar (para el mensaje)
        $guardados = 0;
        $sinMarcar = 0;

        // Recorremos el arreglo de alumnos que envió el formulario
        foreach ($request->asistencias as $alumno_id => $datos) {
            if (! isset($alumnos[$alumno_id])) {
                continue;   // no es de este grupo
            }

            $estadoNuevo = $datos['estado'] ?? null;

            // Buscamos si ya tenía asistencia ese día, si no, preparamos una nueva
            $asistencia = Asistencia::firstOrNew([
                'horario_id' => $horario->id,
                'user_id'    => $alumno_id,
                'fecha'      => $request->fecha,
                'tipo'       => 'Clase'
            ]);

            // Se guarda cómo estaba antes para decidir después si hay que avisar.
            $esNuevo        = !$asistencia->exists;
            $estadoAnterior = $asistencia->estado;

            // Sin registro y sin marcar: se queda sin registro. Antes se guardaba
            // "presente" por omisión, y con guardar la lista a media clase quien no
            // vino quedaba con asistencia. Así, mientras dure la clase todavía puede
            // registrarse; si no, cuenta como falta (App\Support\FaltasSinRegistro).
            if ($esNuevo && ! $estadoNuevo) {
                $sinMarcar++;
                continue;
            }

            // Si es un registro completamente nuevo, marcamos la hora de registro y el
            // laboratorio. La hora es la real si se pasa lista durante la clase; si es
            // una lista de otro día (desde el historial), la de inicio de esa clase.
            if ($esNuevo) {
                $asistencia->fecha_hora_registro = $horario->horaDeRegistroDeLista($request->fecha);
                $asistencia->centro_computo_id = $horario->centro_computo_id;
            }

            // Actualizamos el estado (presente/falta/justificado); si ya tenía
            // registro y no llegó ninguno, se queda el que tenía.
            $asistencia->estado = $estadoNuevo ?? $asistencia->estado;

            // Actualizamos el comentario (si el profe escribió algo en la alerta).
            // Con array_key_exists y no isset: una nota borrada llega vacía (null) y
            // antes se quedaba la anterior, no había forma de quitarla.
            if (array_key_exists('comentario', $datos)) {
                $asistencia->comentario = $datos['comentario'];
            }

            $asistencia->save();
            $guardados++;

            // ---------------------------------------------------------------
            //  Aviso al alumno, SÓLO si su situación cambió.
            //
            //  Así se evita el correo duplicado: si el alumno ya había registrado
            //  su asistencia por su cuenta y el profesor confirma lo mismo, no
            //  hay nada nuevo que contarle y no se le escribe. En cambio, si el
            //  profesor lo cambia a falta, o si el alumno no se había registrado,
            //  sí se entera.
            // ---------------------------------------------------------------
            $huboCambio = $esNuevo || $estadoAnterior !== $asistencia->estado;

            if ($huboCambio) {
                AvisosCorreo::asistenciaDeClase(
                    $alumnos[$alumno_id],
                    $asistencia,
                    'profesor',
                    $avisados
                );
                $avisados++;
            }
        }

        if ($guardados === 0 && $sinMarcar > 0) {
            return back()->with('error', 'No marcaste a ningún alumno. Marca su asistencia antes de guardar.');
        }

        $mensaje = '¡La lista de asistencia se ha guardado correctamente!';

        if ($sinMarcar > 0) {
            $mensaje .= ' ' . $sinMarcar . ($sinMarcar === 1 ? ' alumno quedó' : ' alumnos quedaron') . ' sin marcar: '
                . ($this->claseEnCurso($horario, $request->fecha)
                    ? 'todavía pueden registrarse mientras dure la clase; si no lo hacen, contará como falta.'
                    : 'cuenta como falta.');
        }

        return back()->with('success', $mensaje);
    }

    /** ¿Es la clase de hoy y todavía no termina? (aún pueden registrarse) */
    protected function claseEnCurso(Horario $horario, string $fecha): bool
    {
        return $fecha === Carbon::today()->toDateString()
            && Carbon::now()->format('H:i:s') <= $horario->hora_fin;
    }

    /**
     * Muestra el reporte estadístico del grupo.
     *
     * Cuenta como "Mi Progreso" del alumno (ver App\Support\EstadisticasDeClase):
     * antes cada día con algún registro contaba para todos, y al alumno inscrito
     * tarde le salían faltas de antes de inscribirse.
     */
    public function verGrupo(Request $request, $horario_id)
    {
        $horario = Horario::with(['materia', 'grupo', 'semestre', 'centroComputo'])
            ->where('id', $horario_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $estadisticas = EstadisticasDeClase::de($horario);

        // A dónde regresa: al pase de lista del que vino, o a Reportes
        $fechaLista = $request->filled('fecha') ? $this->fechaValida($request->input('fecha')) : null;
        $volver = $fechaLista
            ? route('profesor.revisar-clase', ['horario_id' => $horario->id, 'fecha' => $fechaLista])
            : route('profesor.reportes', ['semestre_id' => $horario->semestre_id]);

        return view('profesor.detalle', [
            'horario' => $horario,
            'materia' => $horario->materia,
            'grupo' => $horario->grupo,
            'totalClases' => $estadisticas['totalClases'],
            'alumnosData' => $estadisticas['alumnos'],
            'resumen' => $estadisticas['resumen'],
            'sesiones' => $estadisticas['horarios'],
            'volver' => $volver,
        ]);
    }

    /**
     * Muestra el horario semanal completo del profesor (SOLO CLASES FIJAS Y DEL SEMESTRE ACTUAL).
     */
    public function horarioSemanal()
    {
        $profesorId = Auth::id();

        // 1. Detectamos cuál es el semestre activo en el sistema
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();

        // 2. Preparamos la consulta base
        $query = Horario::with(['materia', 'grupo', 'centroComputo'])
            ->where('user_id', $profesorId)
            ->where('tipo_reserva', 'recurrente');

        // 3. Si hay un semestre activo, filtramos para que solo salgan esas clases
        if ($semestreActivo) {
            $query->where('semestre_id', $semestreActivo->id);
        }

        // 4. Ejecutamos la consulta y ordenamos
        $clases = $query->orderBy('hora_inicio')->get();

        // 5. Agrupamos por el día de la semana
        $horarioPorDia = $clases->groupBy('dia_semana');

        // 6. Definimos los días oficiales
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        // 7. Para resaltar el día de hoy en el calendario
        $nombresDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $diaHoy = $nombresDias[Carbon::now()->dayOfWeekIso];

        // 8. Las reservas especiales (una clase en una fecha concreta) no salían en
        //    ningún lado del calendario. Las que faltan por darse: las de esta semana
        //    van en su día y todas en la lista de abajo.
        $especiales = $semestreActivo
            ? Horario::with(['materia', 'grupo', 'centroComputo'])
                ->where('user_id', $profesorId)
                ->where('semestre_id', $semestreActivo->id)
                ->where('tipo_reserva', 'especial')
                ->whereDate('fecha_especial', '>=', Carbon::today()->toDateString())
                ->orderBy('fecha_especial')
                ->orderBy('hora_inicio')
                ->get()
            : collect();

        $finDeSemana = Carbon::now()->endOfWeek()->toDateString();
        $especialesPorDia = $especiales
            ->filter(fn($clase) => Carbon::parse($clase->fecha_especial)->toDateString() <= $finDeSemana)
            ->groupBy(fn($clase) => $nombresDias[Carbon::parse($clase->fecha_especial)->dayOfWeekIso]);

        return view('profesor.horario', compact('horarioPorDia', 'diasSemana', 'diaHoy', 'especiales', 'especialesPorDia'));
    }

    /**
     * Muestra la vista de Reportes con el filtro por semestre.
     */
    public function reportes(Request $request)
    {
        $profesorId = Auth::id();

        // 1. Obtenemos todos los semestres para llenar el <select>
        // Los ordenamos por ID descendente para que los más nuevos salgan primero
        $semestres = \App\Models\Semestre::orderBy('id', 'desc')->get();

        // 2. Buscamos el semestre activo actual para ponerlo por defecto
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();

        // Obtenemos el semestre que el usuario seleccionó (si existe), si no, usamos el activo
        $pedido = $request->input('semestre_id');
        $semestreSeleccionadoId = (is_numeric($pedido) ? $semestres->firstWhere('id', (int) $pedido)?->id : null)
            ?? ($semestreActivo ? $semestreActivo->id : null);

        $clasesUnicas = collect();

        if ($semestreSeleccionadoId) {
            // 3. Buscamos las clases del profesor en ese semestre
            $clasesUnicas = Horario::with(['materia', 'grupo', 'centroComputo'])
                ->where('user_id', $profesorId)
                ->where('semestre_id', $semestreSeleccionadoId)
                ->where('tipo_reserva', 'recurrente') // Solo evaluamos clases fijas
                ->orderBy('hora_inicio')
                ->get()
                // Mágia: Filtramos para que no salgan repetidas si da la misma clase varios días
                ->unique(function ($item) {
                    return $item->materia_id . '-' . $item->grupo_id;
                })
                ->sortBy(fn($clase) => Str::lower(Str::ascii($clase->materia->nombre_materia ?? '')))
                ->values();

            // 4. Cómo va cada una, para verlo sin descargar nada (mismo cálculo que
            //    sus estadísticas y que "Mi Progreso" de los alumnos)
            foreach ($clasesUnicas as $clase) {
                $clase->estadisticas = EstadisticasDeClase::de($clase);
            }
        }

        // 5. Todas juntas, para el resumen de arriba
        $general = [
            'clases' => $clasesUnicas->count(),
            'alumnos' => $clasesUnicas->sum(fn($c) => $c->estadisticas['resumen']['alumnos']),
            'en_riesgo' => $clasesUnicas->sum(fn($c) => $c->estadisticas['resumen']['en_riesgo']),
            'impartidas' => $clasesUnicas->sum(fn($c) => $c->estadisticas['totalClases']),
        ];

        return view('profesor.reportes', compact('semestres', 'semestreSeleccionadoId', 'clasesUnicas', 'general'));
    }

    private function limpiarNombreArchivo($texto)
    {
        // Normaliza encoding
        $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');

        // Quita acentos (Laravel)
        $texto = Str::ascii($texto);

        // Reemplaza espacios y caracteres raros
        $texto = preg_replace('/[^A-Za-z0-9\-]/', '_', $texto);

        // Evita múltiples guiones bajos
        $texto = preg_replace('/_+/', '_', $texto);

        return trim($texto, '_');
    }

    /**
     * Genera el PDF de la lista de alumnos de una clase.
     */
    public function descargarListaPdf($horario_id)
    {
        $profesorId = Auth::id();

        // 1. Buscamos la clase, asegurándonos de que le pertenezca a este profesor
        $horario = Horario::with(['materia', 'grupo.alumnos', 'user', 'semestre'])
            ->where('id', $horario_id)
            ->where('user_id', $profesorId)
            ->firstOrFail();

        // 2. Obtenemos los alumnos y los ordenamos alfabéticamente por Apellidos
        $alumnos = $horario->grupo->alumnos->sortBy([
            ['apellido_paterno', 'asc'],
            ['apellido_materno', 'asc'],
            ['name', 'asc'],
        ])->values();

        // 2.5 El semestre de la clase para el encabezado del PDF. Antes era siempre
        //     el activo: la lista de un semestre pasado decía el periodo actual.
        $semestre = $horario->semestre ?? Semestre::activo();

        // 3. Cargamos la vista y le pasamos los datos (Añadido $semestre)
        $pdf = Pdf::loadView('profesor.pdf.lista_alumnos', compact('horario', 'alumnos', 'semestre'));

        // (Opcional) Configuramos el papel en tamaño carta
        $pdf->setPaper('letter', 'portrait');

        // 4. Generamos el nombre del archivo (Ej: Lista_Redes_5A.pdf)
        $nombreMateriaLimpio = $this->limpiarNombreArchivo($horario->materia->nombre_materia);
        $grupoLimpio = $this->limpiarNombreArchivo($horario->grupo->nombre_grupo);

        $nombreArchivo = "Lista_{$nombreMateriaLimpio}_{$grupoLimpio}.pdf";

        // 5. Forzamos la descarga
        return $pdf->download($nombreArchivo);
    }

    /**
     * Genera el Excel de la lista de alumnos de una clase.
     */
    public function descargarListaExcel($horario_id)
    {
        $profesorId = Auth::id();

        // 1. Buscamos la clase
        $horario = Horario::with(['materia', 'grupo.alumnos'])
            ->where('id', $horario_id)
            ->where('user_id', $profesorId)
            ->firstOrFail();

        // 2. Obtenemos los alumnos ordenados alfabéticamente
        $alumnos = $horario->grupo->alumnos->sortBy([
            ['apellido_paterno', 'asc'],
            ['apellido_materno', 'asc'],
            ['name', 'asc'],
        ])->values();

        // 3. Generamos el nombre del archivo
        $nombreMateriaLimpio = $this->limpiarNombreArchivo($horario->materia->nombre_materia);
        $grupoLimpio = $this->limpiarNombreArchivo($horario->grupo->nombre_grupo);

        $nombreArchivo = "Lista_{$nombreMateriaLimpio}_{$grupoLimpio}.xlsx";

        // 4. Descargamos el Excel usando la clase que creamos en el Paso 2
        return Excel::download(new ListaAlumnosExport($alumnos), $nombreArchivo);
    }

    /**
     * Calcula las estadísticas de una clase (Función de ayuda interna).
     */
    private function calcularEstadisticas($horario_id, $profesorId)
    {
        $horario = Horario::with(['materia', 'grupo', 'user', 'semestre'])
            ->where('id', $horario_id)
            ->where('user_id', $profesorId)
            ->firstOrFail();

        // El mismo cálculo que la pantalla de estadísticas y que "Mi Progreso"
        $estadisticas = EstadisticasDeClase::de($horario);

        return ['horario' => $horario, 'totalClases' => $estadisticas['totalClases'], 'alumnosData' => $estadisticas['alumnos']];
    }

    /**
     * Genera el PDF de Estadísticas.
     */
    public function descargarEstadisticasPdf($horario_id)
    {
        $datos = $this->calcularEstadisticas($horario_id, Auth::id());

        // El semestre de la clase (antes siempre el activo, aunque fuera de otro periodo)
        $datos['semestre'] = $datos['horario']->semestre ?? Semestre::activo();

        $pdf = Pdf::loadView('profesor.pdf.estadisticas', $datos)
            ->setPaper('letter', 'portrait');

        $materia = $this->limpiarNombreArchivo($datos['horario']->materia->nombre_materia);
        $grupo   = $this->limpiarNombreArchivo($datos['horario']->grupo->nombre_grupo);

        $nombreArchivo = "Estadisticas_{$materia}_{$grupo}.pdf";

        return $pdf->download($nombreArchivo);
    }

    /**
     * Genera el Excel de Estadísticas.
     */
    public function descargarEstadisticasExcel($horario_id)
    {
        $datos = $this->calcularEstadisticas($horario_id, Auth::id());

        $materia = $this->limpiarNombreArchivo($datos['horario']->materia->nombre_materia);
        $grupo   = $this->limpiarNombreArchivo($datos['horario']->grupo->nombre_grupo);

        $nombreArchivo = "Estadisticas_{$materia}_{$grupo}.xlsx";

        return Excel::download(new EstadisticasExport($datos['alumnosData']), $nombreArchivo);
    }
}
