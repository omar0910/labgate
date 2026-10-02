<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Support\Busqueda;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\CentroComputo;
use App\Models\AsistenciaProfesor;
use App\Services\AvisosCorreo;
use App\Support\AgendaSemanal;
use App\Support\EstadoDeLaboratorios;
use App\Support\ListaDelDia;
use App\Support\RegistroDeProfesor;
use App\Models\Incidencia;
use App\Models\Semestre; // Importamos el modelo
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProfesoresImport;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // --- 1. SEMESTRE ACTIVO ---
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $hoy = Carbon::now()->format('Y-m-d');

        // --- 2. LA SEMANA EN CURSO ---
        // Antes el progreso dividía las clases con "asistió" de esta semana entre TODOS
        // los horarios del semestre, así que el jueves marcaba 24% aunque todo iba al
        // corriente: contaba las clases que aún no tocaban, las de días inhábiles y
        // las anteriores a que el horario existiera, y nunca las justificadas.
        $semana = AgendaSemanal::de($semestreActivo, Carbon::now());

        // --- 3. KPIS GENERALES ---
        // Sólo cuentas activas (los dados de baja no se cuentan)
        $totalAlumnos    = User::where('rol', 'Alumno')->activos()->count();
        $totalProfesores = User::where('rol', 'Profesor')->activos()->count();

        // Las clases de hoy salen de la misma agenda: sin días inhábiles y sin las
        // reservas especiales de otras fechas (antes salían cada semana).
        $clasesDeHoy = $semana['clases']->where('fecha', $hoy)->values();
        $clasesHoy = $clasesDeHoy->count();
        $motivoInhabilHoy = $semana['inhabiles'][$hoy] ?? null;
        $siguienteClase = $clasesDeHoy->firstWhere('estado', 'proxima');

        // Total de Uso Libre filtrado por el Semestre Activo
        $totalUsoLibre = Asistencia::where('tipo', 'Uso Libre')
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                return $query->whereBetween('fecha', [$semestreActivo->fecha_inicio, $semestreActivo->fecha_fin]);
            })->count();

        $usoLibreHoy = Asistencia::where('tipo', 'Uso Libre')->whereDate('fecha', $hoy)->count();


        // --- 5. PROFESORES CON MÁS FALTAS (ACUMULADO DEL SEMESTRE) ---
        // El mismo cálculo que ve el encargado en su inicio (ver AsistenciaProfesor)
        $profesoresFaltas = AsistenciaProfesor::rankingDeFaltas($semestreActivo);

        // --- 6. ACTIVIDAD RECIENTE (USO LIBRE) ---
        $ultimosUsoLibre = Asistencia::with(['user', 'centroComputo'])
            ->where('tipo', 'Uso Libre')
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                return $query->whereBetween('fecha', [$semestreActivo->fecha_inicio, $semestreActivo->fecha_fin]);
            })
            ->latest('created_at')
            ->take(5)
            ->get();

        // --- 7. LOS LABORATORIOS AHORA Y LAS FALLAS PENDIENTES ---
        // Lo mismo que ve el encargado en su inicio (ver EstadoDeLaboratorios)
        $laboratorios = EstadoDeLaboratorios::ahora(EstadoDeLaboratorios::clasesEnCurso($semana));
        $fallasPendientes = Incidencia::where('estado', 'pendiente')->count();

        return view('admin.dashboard', compact(
            'totalAlumnos',
            'totalProfesores',
            'clasesHoy',
            'clasesDeHoy',
            'motivoInhabilHoy',
            'siguienteClase',
            'totalUsoLibre',
            'usoLibreHoy',
            'profesoresFaltas',
            'ultimosUsoLibre',
            'semana',
            'semestreActivo',
            'laboratorios',
            'fallasPendientes'
        ));
    }

    /**
     * Semana en curso: todas las clases de la semana, día por día, con su estado.
     * Es el "Ver detalle completo" del progreso semanal del panel.
     */
    public function semana(Request $request)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $centros = CentroComputo::orderBy('nombre_centro')->get();
        $centroSeleccionadoId = $request->input('centro_id', 'todos');

        // Cualquier día de la semana que se quiere ver (por omisión, ésta)
        $texto = (string) $request->input('semana', '');
        $dia = Carbon::today();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            try {
                $dia = Carbon::createFromFormat('Y-m-d', $texto)->startOfDay();
            } catch (\Exception $e) {
                // Fecha imposible (p. ej. 2026-02-31): se queda la semana actual
            }
        }

        $semana = AgendaSemanal::de($semestreActivo, $dia, $centroSeleccionadoId);

        // Para ver sólo un estado (p. ej. las que faltan por registrar)
        $estadoFiltro = array_key_exists($request->input('estado'), AgendaSemanal::ESTADOS) ? $request->input('estado') : null;

        return view('admin.semana', compact('semana', 'semestreActivo', 'centros', 'centroSeleccionadoId', 'estadoFiltro'));
    }

    /** 
    public function reporteProfesores()
    {
        $profesores = User::where('rol', 'Profesor')->orderBy('name')->get();
        return view('admin.reportes.profesores', compact('profesores'));
    }*/

    public function reporteProfesores(Request $request)
    {
        // La consulta base va en una función porque, si la búsqueda no encuentra
        // nada, Busqueda la reconstruye para reintentarla tolerando erratas.
        // Por omisión, los activos; con ?bajas=1, los dados de baja (para reactivarlos).
        $verBajas = $request->boolean('bajas');

        $consultaBase = function () use ($verBajas) {
            return User::where('rol', 'Profesor')->where('activo', ! $verBajas)->orderBy('name');
        };

        // Palabra por palabra, así el nombre completo del profesor también encuentra.
        $profesores = Busqueda::paginar(
            $consultaBase,
            $request->input('search'),
            ['name', 'apellido_paterno', 'apellido_materno', 'username', 'rfc'],
            10
        );

        $totalBajas = User::where('rol', 'Profesor')->dadosDeBaja()->count();

        return view('admin.reportes.profesores', compact('profesores', 'verBajas', 'totalBajas'));
    }

    /** public function reporteUsoLibre()
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        $registros = Asistencia::with(['user', 'centroComputo'])
            ->where('tipo', 'Uso Libre')
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                return $query->whereBetween('fecha', [$semestreActivo->fecha_inicio, $semestreActivo->fecha_fin]);
            })
            ->orderByDesc('fecha_hora_registro')
            ->paginate(20);

        return view('admin.reportes.uso_libre', compact('registros'));
    } */

    public function reporteUsoLibre(Request $request)
    {
        // 1. Recibimos lo que el usuario escribió en el buscador y el filtro de fecha
        $search = $request->input('search');
        $fecha = $request->input('fecha');

        // 2. Buscamos el semestre activo
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // 3. Construimos la consulta con los filtros. Va en una función porque,
        // si la búsqueda no encuentra nada, Busqueda la reconstruye para
        // reintentarla tolerando erratas.
        $consultaBase = function () use ($semestreActivo, $fecha) {
            return Asistencia::with(['user', 'centroComputo'])
                ->where('tipo', 'Uso Libre')
                ->when($semestreActivo, function ($query) use ($semestreActivo) {
                    return $query->whereBetween('fecha', [$semestreActivo->fecha_inicio, $semestreActivo->fecha_fin]);
                })
                // FILTRO DE FECHA
                ->when($fecha, function ($query) use ($fecha) {
                    return $query->whereDate('fecha', $fecha);
                })
                ->orderByDesc('fecha_hora_registro');
        };

        // El buscador mira los datos del alumno, palabra por palabra.
        $registros = Busqueda::paginar(
            $consultaBase,
            $search,
            ['user.name', 'user.apellido_paterno', 'user.apellido_materno', 'user.matricula'],
            20
        );

        return view('admin.reportes.uso_libre', compact('registros'));
    }

    /**
     * Reporte: Clases de HOY (Detallado)
     */
    /**
     * Reporte: Clases de HOY y Calendario Interactivo (Panel Admin)
     */
    public function reporteClasesHoy(Request $request)
    {
        $centros = \App\Models\CentroComputo::all();

        // 1. Capturar Fecha y Centro desde el formulario (una fecha inválida escrita
        //    en la dirección daba error 500: ahora se usa hoy)
        $fechaSeleccionada = RegistroDeProfesor::fechaValida($request->input('fecha')) ?? \Carbon\Carbon::today()->format('Y-m-d');
        $centroSeleccionadoId = is_scalar($request->input('centro_id')) ? $request->input('centro_id') : 'todos';

        // 2. Mapeo de Día
        $diasMap = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $diaNombre = $diasMap[\Carbon\Carbon::parse($fechaSeleccionada)->dayOfWeekIso];

        // 3. El semestre activo manda: si la fecha consultada cae fuera del periodo
        //    de clases, no hay agenda que mostrar (mismo criterio que los Jobs de correo).
        $semestreActivo = Semestre::activo();
        $fueraDePeriodo = !$semestreActivo || !$semestreActivo->contieneFecha($fechaSeleccionada);

        // 4. Consulta de Clases basada en el día seleccionado, dentro del semestre activo
        if ($fueraDePeriodo) {
            $clases = collect();
        } else {
            $query = \App\Models\Horario::with(['materia', 'user', 'grupo', 'centroComputo'])
                ->where('semestre_id', $semestreActivo->id)
                ->queTocanEl($fechaSeleccionada);

            // Si seleccionó un laboratorio específico, filtramos. Si no, salen todos.
            if ($centroSeleccionadoId != 'todos') {
                $query->where('centro_computo_id', $centroSeleccionadoId);
            }

            $clases = $query->orderBy('hora_inicio')->get();
        }

        // Lo registrado del profesor y cuántos alumnos se registraron en cada clase
        // (antes se consultaba clase por clase dentro de la vista)
        [$reportes, $registrados] = RegistroDeProfesor::delDia($clases, $fechaSeleccionada);

        // 5. Retornamos la vista correcta enviando todas las variables nuevas
        return view('admin.reportes.clases_hoy', compact('clases', 'centros', 'centroSeleccionadoId', 'fechaSeleccionada', 'diaNombre', 'semestreActivo', 'fueraDePeriodo', 'reportes', 'registrados'));
    }

    /**
     * Guarda el estado del profesor desde el panel de Admin
     */
    public function registrarEstadoClase(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:asistio,falta,justificado,retardo',
            'fecha' => 'required|date_format:Y-m-d',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'observaciones.max' => 'La nota al docente puede tener hasta 1000 caracteres.',
        ]);

        // Antes una clase que no existe daba error 500
        $horario = Horario::with(['semestre', 'user'])->findOrFail($id);
        $fecha = $request->fecha;

        // Que la clase se dé ese día, dentro de su semestre, y nada de "asistió" por adelantado
        if ($problema = RegistroDeProfesor::problema($horario, $fecha, $request->estado)) {
            return redirect()->back()->with('error', $problema);
        }

        // Lo mismo que en "Gestión de Clases" del encargado (App\Support\RegistroDeProfesor)
        $quitadas = RegistroDeProfesor::registrar($horario, $fecha, $request->estado, $request->observaciones);

        return redirect()->back()->with('success', 'Estado actualizado correctamente (Modificado por Admin).'
            . ($quitadas > 0 ? " Se quitaron las asistencias de {$quitadas} " . ($quitadas === 1 ? 'alumno' : 'alumnos') . ' (la clase no se dio).' : ''));
    }

    /**
     * Muestra la lista de alumnos desde el panel de Admin
     */
    public function verAsistenciaClase(Request $request, $id)
    {
        // Una fecha inválida en la dirección daba error 500: ahora se usa hoy
        $fecha = RegistroDeProfesor::fechaValida($request->input('fecha')) ?? \Carbon\Carbon::today()->format('Y-m-d');
        $horario = \App\Models\Horario::with(['materia', 'grupo', 'user', 'centroComputo'])->findOrFail($id);

        // Cada alumno y cómo quedó ese día, igual que en la del encargado
        // (App\Support\ListaDelDia): quien no tiene registro ya no sale siempre "Falta".
        $lista = ListaDelDia::de($horario, $fecha);

        return view('admin.reportes.asistencia_clase', compact('horario', 'lista', 'fecha'));
    }

    /**
     * Importar lista de Profesores desde Excel
     */
    public function importarProfesores(Request $request)
    {
        // 1. Aumentar memoria y tiempo para que no falle con archivos grandes
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // 2. Validar que el archivo exista y sea de Excel
        $request->validate([
            'archivo_excel' => 'required|mimes:xlsx,csv,xls'
        ]);

        // 3. Ejecutar la importación
        try {
            $importador = new ProfesoresImport;

            // Todo o nada: si el archivo falla a la mitad no quedan profesores sueltos.
            DB::transaction(function () use ($importador, $request) {
                Excel::import($importador, $request->file('archivo_excel'));
            });

            $mensaje = 'Importación terminada: ' . $importador->creados . ' profesores nuevos, '
                . $importador->actualizados . ' actualizados.';

            // Los que estaban dados de baja y vienen en la lista oficial se reactivan
            if ($importador->reactivados > 0) {
                $mensaje .= ' ' . ($importador->reactivados == 1 ? 'Se reactivó 1 profesor que estaba dado de baja.'
                    : 'Se reactivaron ' . $importador->reactivados . ' profesores que estaban dados de baja.');
            }

            if ($importador->ignorados > 0) {
                $mensaje .= ' (' . $importador->ignorados . ' filas sin RFC se omitieron.)';
            }

            return back()->with('success', $mensaje);
        } catch (\Exception $e) {
            // Si algo sale mal, capturamos el error para no mostrar una pantalla rota
            return back()->with('error', 'Hubo un error al procesar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar el formulario para crear un nuevo profesor
     */
    public function createProfesor()
    {
        // Las academias que ya existen, para ofrecerlas en el buscador y no tener
        // el mismo departamento escrito de varias maneras.
        $academias = self::academiasRegistradas();

        return view('admin.profesores.create', compact('academias'));
    }

    /**
     * Nombres de academia distintos que ya están capturados, ordenados alfabéticamente.
     */
    public static function academiasRegistradas()
    {
        return User::where('rol', 'Profesor')
            ->whereNotNull('academia')
            ->where('academia', '!=', '')
            ->distinct()
            ->orderBy('academia')
            ->pluck('academia');
    }

    /**
     * Guardar el nuevo profesor en la base de datos
     */
    public function storeProfesor(Request $request)
    {
        // 1. VALIDACIÓN
        $request->validate([
            'rfc'              => 'required|string|max:20|unique:users,rfc',
            'name'             => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'academia'         => 'nullable|string|max:255',
            'username'         => 'required|string|max:255|unique:users,username',
            'email'            => 'required|email|max:255|unique:users,email',
            'password'         => 'nullable|string|min:8', // Es opcional en el formulario
        ]);

        // 2. CREACIÓN DEL USUARIO PROFESOR
        //
        // Se usa mb_strtoupper y no strtoupper: el segundo solo entiende el alfabeto
        // inglés y dejaría "JOSé MARíA" en los nombres con acento.
        User::create([
            'rfc'              => mb_strtoupper((string) $request->rfc, 'UTF-8'),
            'name'             => mb_strtoupper((string) $request->name, 'UTF-8'),
            'apellido_paterno' => mb_strtoupper((string) $request->apellido_paterno, 'UTF-8'),
            'apellido_materno' => mb_strtoupper((string) $request->apellido_materno, 'UTF-8'),
            'academia'         => mb_strtoupper((string) $request->academia, 'UTF-8'),
            'username'         => strtolower($request->username),
            'email'            => strtolower($request->email),
            // Si el admin no escribe contraseña, se usa el RFC por defecto
            'password'         => $request->password ? \Illuminate\Support\Facades\Hash::make($request->password) : \Illuminate\Support\Facades\Hash::make($request->rfc),
            'rol'              => 'Profesor',
            'matricula'        => null,
        ]);

        return redirect()->route('admin.reportes.profesores')
            ->with('success', '¡Profesor registrado exitosamente!');
    }

    /**
     * Mostrar el formulario para editar un profesor existente
     */
    public function editProfesor(User $profesor)
    {
        // Las mismas academias que en el alta, para no acabar con el mismo
        // departamento escrito de varias maneras.
        $academias = self::academiasRegistradas();

        return view('admin.profesores.edit', compact('profesor', 'academias'));
    }

    /**
     * Actualizar los datos del profesor en la base de datos
     */
    public function updateProfesor(Request $request, User $profesor)
    {
        // 1. VALIDACIÓN
        $request->validate([
            'rfc' => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('users')->ignore($profesor->id)
            ],
            'name'             => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'academia'         => 'nullable|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('users')->ignore($profesor->id)
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('users')->ignore($profesor->id)
            ],
            'password'         => 'nullable|string|min:8', // Opcional
        ]);

        // 2. PREPARAR DATOS
        $data = [
            'rfc'              => mb_strtoupper((string) $request->rfc, 'UTF-8'),
            'name'             => mb_strtoupper((string) $request->name, 'UTF-8'),
            'apellido_paterno' => mb_strtoupper((string) $request->apellido_paterno, 'UTF-8'),
            'apellido_materno' => mb_strtoupper((string) $request->apellido_materno, 'UTF-8'),
            'academia'         => mb_strtoupper((string) $request->academia, 'UTF-8'),
            'username'         => strtolower($request->username),
            'email'            => strtolower($request->email),
        ];

        // 3. ACTUALIZAR CONTRASEÑA SOLO SI SE ESCRIBIÓ UNA NUEVA
        if (!empty($request->password)) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        // 4. GUARDAR
        $profesor->update($data);

        return redirect()->route('admin.reportes.profesores')
            ->with('success', '¡Datos del profesor actualizados correctamente!');
    }

    public function destroyUsoLibre($id)
    {
        // Busca el registro de asistencia y lo elimina
        $registro = \App\Models\Asistencia::findOrFail($id);
        $registro->delete();

        // Regresa a la vista anterior con un mensaje de éxito
        return redirect()->back()->with('success', 'Registro de uso libre eliminado correctamente.');
    }
}
