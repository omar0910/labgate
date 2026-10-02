<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use App\Models\User;
use App\Models\Asistencia;
use App\Services\AvisosCorreo;
use App\Models\Semestre;
use App\Support\Busqueda;
use App\Support\RegistroDeProfesor;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BitacoraController extends Controller
{
    /**
     * 1. EL BUSCADOR DE CLASES (Paso 1)
     */
    public function index(Request $request)
    {
        // 1. Buscamos cuál es el semestre activo actualmente
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // 2. Los horarios del semestre activo. La consulta base va en una función
        // porque, si la búsqueda no encuentra nada, Busqueda la reconstruye para
        // reintentarla tolerando erratas.
        $consultaBase = function () use ($semestreActivo) {
            $query = Horario::with(['materia', 'user', 'grupo.semestre', 'centroComputo']);

            if ($semestreActivo) {
                $query->where('semestre_id', $semestreActivo->id);
            }

            return $query;
        };

        // 3. Se busca por materia, grupo o profesor, palabra por palabra. Antes hacía
        // falta un CONCAT para el nombre completo del profesor; ahora cada palabra se
        // busca por separado y eso ya no es necesario.
        $horarios = Busqueda::todos(
            $consultaBase,
            $request->input('search'),
            [
                'materia.nombre_materia',
                'grupo.nombre_grupo',
                'user.name',
                'user.apellido_paterno',
                'user.apellido_materno',
            ]
        );

        // 4. Una fila por ASIGNATURA, no por día: una materia de lunes, miércoles y
        // jueves son tres horarios en el sistema, y antes salía tres veces. Cada
        // fila lleva todas sus sesiones; la lista se sigue pasando por sesión.
        $todas = $horarios->groupBy(fn($h) => $h->claveDeAsignatura())
            ->map(function ($sesiones) {
                $sesiones = $sesiones->sortBy(fn($h) => $h->ordenDeSesion())->values();
                $primera = $sesiones->first();

                return (object) [
                    'id'           => $primera->id,   // la bitácora se abre desde cualquiera de sus sesiones
                    'materia'      => $primera->materia,
                    'grupo'        => $primera->grupo,
                    'user'         => $primera->user,
                    'sesiones'     => $sesiones,
                    'laboratorios' => $sesiones->map(fn($h) => $h->centroComputo->nombre_centro ?? null)->filter()->unique()->values(),
                ];
            })
            ->sortBy(fn($a) => mb_strtolower(($a->materia->nombre_materia ?? '') . '|' . ($a->grupo->nombre_grupo ?? '')))
            ->values();

        $porPagina = 10;
        $pagina = LengthAwarePaginator::resolveCurrentPage();

        $asignaturas = new LengthAwarePaginator(
            $todas->forPage($pagina, $porPagina)->values(),
            $todas->count(),
            $porPagina,
            $pagina,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pasamos también el semestre activo a la vista por si quieres mostrar el nombre
        return view('admin.monitor.buscadorclases', compact('asignaturas', 'semestreActivo'));
    }

    /**
     * 2. EL PERFIL DE LA CLASE Y SU HISTORIAL DE FECHAS (Paso 2)
     *
     * Se abre desde cualquier sesión de la asignatura y muestra todas: los días
     * en que se da, el pase de lista de hoy y el historial de todas juntas.
     */
    public function show($id)
    {
        $horario = Horario::with(['materia', 'user', 'grupo.semestre', 'centroComputo'])->findOrFail($id);

        $sesiones = Horario::with('centroComputo')
            ->deLaMismaAsignatura($horario)
            ->get()
            ->sortBy(fn($h) => $h->ordenDeSesion())
            ->values();

        // Si por alguna razón no se encontraran (datos incompletos), al menos ésta.
        if ($sesiones->isEmpty()) {
            $sesiones = collect([$horario]);
        }

        // Las sesiones que tocan HOY: puede haber más de una (mañana y tarde).
        $hoy = now()->format('Y-m-d');
        $sesionesDeHoy = $sesiones->filter(function ($sesion) use ($hoy) {
            return $sesion->tipo_reserva === 'especial'
                ? substr((string) $sesion->fecha_especial, 0, 10) === $hoy
                : $sesion->numeroDeDia() === now()->dayOfWeekIso;
        })->values();

        // Todas las fechas en que ya se pasó lista, de cualquiera de sus sesiones
        $fechasHistorial = Asistencia::whereIn('horario_id', $sesiones->pluck('id'))
            ->where('tipo', 'Clase')
            ->select('horario_id', 'fecha')
            ->distinct()
            ->orderBy('fecha', 'desc')
            ->get();

        return view('admin.monitor.buscadorshow', compact('horario', 'sesiones', 'sesionesDeHoy', 'fechasHistorial'));
    }

    /**
     * 3. LA LISTA DE ALUMNOS PARA UNA FECHA ESPECÍFICA (Paso 3)
     */
    public function asistencia($horario_id, $fecha)
    {
        $horario = Horario::with(['materia', 'grupo', 'centroComputo', 'semestre'])->findOrFail($horario_id);

        // Una fecha real, que ya llegó y dentro del semestre de la clase. Puede no
        // ser su día: aquí se capturan las reposiciones. Antes con 2026-13-45 tronaba
        // y se podía guardar la lista de una fecha que todavía no llega.
        $problema = RegistroDeProfesor::fechaValida($fecha)
            ? $horario->problemaParaLaFecha($fecha, 'No se puede pasar lista de una fecha que aún no llega.', false)
            : 'La fecha de esa lista no es válida.';

        if ($problema) {
            return redirect()->route('admin.bitacora.show', $horario->id)->with('error', $problema);
        }

        // Formateamos la fecha para que sea amigable a la vista
        $fechaObj = Carbon::parse($fecha);

        // CONSULTA MAESTRA: Traemos a los alumnos inscritos en el grupo de este horario
        // Usamos un JOIN directo a tu tabla 'alumno_grupo' para que no falle.
        // CONSULTA MAESTRA: Traemos a los alumnos inscritos en el grupo de este horario
        $alumnos = User::where('rol', 'Alumno')
            ->where('users.activo', true)   // sin los dados de baja
            ->join('alumno_grupo', 'users.id', '=', 'alumno_grupo.user_id')
            ->where('alumno_grupo.grupo_id', $horario->grupo_id)
            ->select('users.*')
            ->orderBy('apellido_paterno', 'asc') // 1. Ordena por Paterno
            ->orderBy('apellido_materno', 'asc') // 2. Luego por Materno
            ->orderBy('name', 'asc')             // 3. Y al final por Nombre
            ->get();

        // Traemos las asistencias que ya existan para ese día y esa clase
        $asistenciasGuardadas = Asistencia::where('horario_id', $horario->id)
            ->where('fecha', $fecha)
            ->where('tipo', 'Clase')
            ->get()
            ->keyBy('user_id'); // Las agrupamos por el ID del alumno para buscarlas fácil

        return view('admin.monitor.asistencia', compact('horario', 'alumnos', 'fecha', 'fechaObj', 'asistenciasGuardadas'));
    }

    /**
     * 4. GUARDAR O ACTUALIZAR LA LISTA (El botón final)
     */
    public function storeAsistencia(Request $request, $horario_id, $fecha)
    {
        $request->validate([
            'asistencias'                  => 'nullable|array',
            'asistencias.*.estado'         => 'nullable|in:presente,falta,justificado',
            'asistencias.*.numero_maquina' => 'nullable|integer|min:1',
            'asistencias.*.equipo_personal' => 'nullable|boolean',
            'asistencias.*.comentario'     => 'nullable|string|max:1000',
        ]);

        $horario = Horario::with('semestre')->findOrFail($horario_id);

        // Sólo en una fecha real, dentro de su semestre y que ya llegó (las
        // reposiciones, en otro día de la semana, sí se permiten)
        $problema = RegistroDeProfesor::fechaValida($fecha)
            ? $horario->problemaParaLaFecha($fecha, 'No se puede pasar lista de una fecha que aún no llega.', false)
            : 'La fecha de esa lista no es válida.';

        if ($problema) {
            return back()->with('error', $problema);
        }

        $asistencias = $request->input('asistencias', []); // Recibimos el arreglo del formulario

        // Sólo alumnos inscritos en el grupo de la clase: un id que no es del grupo
        // (o que no existe) creaba registros sueltos o tronaba al guardar.
        $alumnos = $horario->grupo
            ? $horario->grupo->alumnos()->whereIn('users.id', array_keys($asistencias))->get()->keyBy('id')
            : collect();

        // Cuenta sólo los avisos que de verdad salen, para escalonarlos en el tiempo.
        $avisados = 0;

        // Si la lista viene de "Clases pendientes", es captura de historial desde las
        // hojas de papel: no se avisa a los alumnos de clases de hace semanas.
        $desdePendientes = $request->input('volver') === 'pendientes';

        // Hora que se anota en los registros NUEVOS de esta lista. Si se captura durante
        // la clase, la hora real; si es de otro momento (una lista pasada, o la de hoy
        // capturada ya terminada la clase), la hora de inicio de la clase en su día.
        // Antes se ponía siempre la hora del guardado, y la "hora pico" de los
        // reportes salía a la hora en que el encargado pasaba las hojas.
        $horaDeRegistro = $horario->horaDeRegistroDeLista($fecha);

        // Recorremos cada alumno enviado desde el formulario
        foreach ($asistencias as $alumno_id => $datos) {
            if (! isset($alumnos[$alumno_id])) {
                continue;
            }

            // Si ya existe se actualiza, y si no, se crea.
            $asistencia = Asistencia::firstOrNew([
                'horario_id' => $horario->id,
                'user_id'    => $alumno_id,
                'fecha'      => $fecha,
                'tipo'       => 'Clase'
            ]);

            // Con equipo personal (su laptop) no se anota número de PC
            $personal = ! empty($datos['equipo_personal']);

            $asistencia->fill([
                'estado'            => $datos['estado'] ?? 'falta', // presente, falta, justificado
                'numero_maquina'    => $personal ? null : ($datos['numero_maquina'] ?? null),
                'equipo_personal'   => $personal,
                'comentario'        => $datos['comentario'] ?? null,
                'centro_computo_id' => $horario->centro_computo_id,
            ]);

            // Al corregir una lista se respeta la hora que ya tenía el registro (por
            // ejemplo, la del alumno que se registró solo al llegar).
            if (! $asistencia->exists || ! $asistencia->fecha_hora_registro) {
                $asistencia->fecha_hora_registro = $horaDeRegistro;
            }

            $asistencia->save();

            // Se avisa al alumno sólo si su situación cambió: al corregir la lista de
            // un día, lo habitual es que se toquen dos o tres alumnos y el resto se
            // guarde igual que estaba. Esos no tienen por qué recibir un correo.
            $huboCambio = $asistencia->wasRecentlyCreated || $asistencia->wasChanged('estado');

            if ($huboCambio && ! $desdePendientes) {
                AvisosCorreo::asistenciaDeClase($alumnos[$alumno_id], $asistencia, 'ajuste', $avisados);
                $avisados++;
            }
        }

        $mensaje = 'La lista de asistencia para el ' . Carbon::parse($fecha)->format('d/m/Y') . ' se ha guardado correctamente.';

        // Se regresa a donde se estaba trabajando.
        if ($desdePendientes) {
            // Con el origen con que se abrió pendientes, para que su "Volver" siga igual
            return redirect()->route('admin.bitacora.pendientes', \App\Support\Origen::parametros($request))
                ->with('success', $mensaje);
        }

        // Si se corrigió desde la lista de Gestión de Clases / Clases de hoy, a esa lista
        if ($request->input('volver') === 'lista') {
            return redirect()->route(
                $request->user()->rol === 'Administrador' ? 'admin.ver-asistencia' : 'encargado.ver-asistencia',
                ['id' => $horario->id, 'fecha' => $fecha, 'centro_id' => $request->input('centro_id', 'todos')]
            )->with('success', $mensaje);
        }

        return redirect()->route('admin.bitacora.show', $horario->id)->with('success', $mensaje);
    }
}
