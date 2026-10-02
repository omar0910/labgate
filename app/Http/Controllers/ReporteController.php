<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Semestre;
use App\Models\CentroComputo;
use App\Models\User;
use App\Models\Materia;
use App\Models\Grupo;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\DocentesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\AsistenciaProfesor;
use App\Support\CalendarioDeClases;

class ReporteController extends Controller
{
    /**
     * Clases que debía dar un horario: en todo el semestre y hasta hoy.
     *
     * Cuenta desde que la clase se registró en el sistema; las fechas anteriores
     * sólo cuentan si ya tienen asistencia capturada. Ver CalendarioDeClases.
     */
    private static function clasesEsperadas($horario, $semestre, array $diasInhabiles, $fechasConRegistro): array
    {
        return CalendarioDeClases::esperadas($horario, $semestre, $diasInhabiles, $fechasConRegistro);
    }

    /**
     * ¿La persona estuvo en el centro? Mismo criterio que Asistencia::presenciales(),
     * para colecciones ya cargadas: fuera faltas y justificados.
     */
    private static function estuvoPresente($asistencia): bool
    {
        return ! in_array($asistencia->estado, ['falta', 'justificado'], true);
    }

    /**
     * Qué asignatura es un horario: misma materia, mismo grupo, mismo docente, y si
     * es fija o reserva especial. En el sistema, una materia que se da lunes y
     * miércoles son dos horarios, pero es una sola asignatura.
     */
    private static function claveDeAsignatura($horario): string
    {
        return $horario->claveDeAsignatura();
    }

    /**
     * Cifras de "Clases agendadas": se cuentan asignaturas, no horarios. Contar
     * horarios hacía que una materia de tres días valiera por tres clases.
     */
    private static function cifrasDeClasesAgendadas($horarios): array
    {
        $fijas = $horarios->where('tipo_reserva', '!=', 'especial');
        $especiales = $horarios->where('tipo_reserva', 'especial');

        return [
            'total'      => $horarios->groupBy(fn($h) => self::claveDeAsignatura($h))->count(),
            'fijas'      => $fijas->groupBy(fn($h) => self::claveDeAsignatura($h))->count(),
            'especiales' => $especiales->groupBy(fn($h) => self::claveDeAsignatura($h))->count(),
            // Cada horario fijo es una sesión a la semana
            'sesiones_por_semana' => $fijas->count(),
        ];
    }

    /**
     * Las asignaturas del periodo, cada una con todos sus días y horas, para el
     * detalle de "Clases agendadas".
     */
    private static function asignaturasDe($horarios)
    {
        $ordenDias = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7];

        return $horarios->groupBy(fn($h) => self::claveDeAsignatura($h))
            ->map(function ($dias) use ($ordenDias) {
                $primero = $dias->first();

                $sesiones = $dias
                    ->sortBy(fn($h) => ($h->fecha_especial ?? '') . ($ordenDias[$h->dia_semana] ?? 9) . $h->hora_inicio)
                    ->map(fn($h) => ($h->fecha_especial
                            ? \Carbon\Carbon::parse($h->fecha_especial)->format('d/m/Y')
                            : $h->dia_semana)
                        . ' ' . ($h->hora_inicio ? \Carbon\Carbon::parse($h->hora_inicio)->format('h:i A') : '--')
                        . ' a ' . ($h->hora_fin ? \Carbon\Carbon::parse($h->hora_fin)->format('h:i A') : '--'))
                    ->values();

                return (object) [
                    'materia'      => $primero->materia,
                    'grupo'        => $primero->grupo,
                    'user'         => $primero->user,
                    'tipo_reserva' => $primero->tipo_reserva,
                    'laboratorios' => $dias->map(fn($h) => $h->centroComputo->nombre_centro ?? null)->filter()->unique()->implode(', '),
                    'sesiones'     => $sesiones,
                ];
            })
            ->sortBy(fn($a) => ($a->materia->nombre_materia ?? '') . '|' . ($a->grupo->nombre_grupo ?? ''))
            ->values();
    }

    /**
     * Muestra el Dashboard principal del Centro de Reportes Analíticos.
     */
    public function index(Request $request)
    {
        $semestres = Semestre::orderBy('id', 'desc')->get();
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);

        // Con esto cargamos los laboratorios y, al mismo tiempo, todas sus computadoras ordenadas
        $centros = CentroComputo::with(['equipos' => function ($query) {
            $query->orderBy('numero_maquina');
        }])->get();
        $materias = Materia::orderBy('nombre_materia')->get();
        $grupos = Grupo::orderBy('nombre_grupo')->get();
        $profesores = User::where('rol', 'Profesor')->orderBy('apellido_paterno')->get();

        $stats = [
            'total_clases' => 0,
            'clases_fijas' => 0,
            'clases_especiales' => 0,
            'docentes_activos' => 0,
            'docentes_fijos' => 0,
            'docentes_especiales' => 0,
            'total_uso_libre' => 0,
            'sesiones_por_semana' => 0,
        ];
        $chartData = ['labels' => [], 'clases' => [], 'usoLibre' => []];

        $clasesDelSemestre = collect();
        $asignaturasDelSemestre = collect();
        $docentesActivos = collect();
        $reporteDocentes = collect();
        $asistenciasDocentes = collect();
        $reporteMaterias = collect();
        $reporteCarreras = collect();
        $registrosUsoLibre = collect();
        // Estas dos sólo se llenaban dentro del if de abajo, así que al entrar
        // sin periodo seleccionado la vista tronaba con "Undefined variable".
        $reporteAlumnos = collect();
        $reporteLaboratorios = [];
        $semestre = null;

        if ($semestreSeleccionadoId) {
            $semestre = Semestre::find($semestreSeleccionadoId);

            if ($semestre) {
                $clasesDelSemestre = \App\Models\Horario::with(['materia', 'grupo', 'user', 'centroComputo'])
                    ->where('semestre_id', $semestreSeleccionadoId)
                    ->get();

                $clasesAgendadas = self::cifrasDeClasesAgendadas($clasesDelSemestre);
                $stats['total_clases'] = $clasesAgendadas['total'];
                $stats['clases_fijas'] = $clasesAgendadas['fijas'];
                $stats['clases_especiales'] = $clasesAgendadas['especiales'];
                $stats['sesiones_por_semana'] = $clasesAgendadas['sesiones_por_semana'];
                $asignaturasDelSemestre = self::asignaturasDe($clasesDelSemestre);

                $docentesActivos = $clasesDelSemestre->pluck('user')->unique('id')->filter();
                $stats['docentes_activos'] = $docentesActivos->count();
                $stats['docentes_fijos'] = $clasesDelSemestre->where('tipo_reserva', 'recurrente')->pluck('user_id')->unique()->count();
                $stats['docentes_especiales'] = $clasesDelSemestre->where('tipo_reserva', 'especial')->pluck('user_id')->unique()->count();

                // Moviendo a los alumnos arriba para usarlos en el Plan B de los docentes.
                // Se cargan UNA vez para todo el reporte (antes esta misma consulta, la
                // más pesada, se hacía dos veces, igual que la de los profesores y la de
                // los días inhábiles).
                $asistenciasAlumnos = \App\Models\Asistencia::with(['user', 'centroComputo', 'horario.materia', 'horario.grupo'])
                    ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                    ->whereHas('user', fn($q) => $q->where('rol', 'Alumno'))
                    ->get();

                // --- INICIO LÓGICA DE DOCENTES (HÍBRIDA + FALLBACK) ---
                $asistenciasDocentes = \App\Models\AsistenciaProfesor::whereIn('horario_id', $clasesDelSemestre->pluck('id'))->get();
                $diasInhabilesData = \App\Models\DiaInhabil::where('semestre_id', $semestre->id)->get();
                $diasInhabiles = $diasInhabilesData->pluck('fecha')->toArray();
                $motivosInhabiles = $diasInhabilesData->pluck('motivo', 'fecha')->toArray();

                // El mismo cálculo que el PDF y el Excel de docentes (antes estaba copiado
                // aquí y allá, y cualquier cambio en uno dejaba de coincidir con el otro).
                $reporteDocentes = collect($this->obtenerDatosDocentes($request)['reporteDocentes'] ?? []);
                // --- FIN LÓGICA DE DOCENTES ---

                // Aquí continúa tu código normal...
                $reporteMaterias = $clasesDelSemestre->groupBy('materia_id')->map(function ($clases) {
                    return [
                        'nombre' => $clases->first()->materia->nombre_materia ?? 'Desconocida',
                        'total_sesiones' => $clases->count(),
                        'total_profesores' => $clases->pluck('user_id')->unique()->count()
                    ];
                })->sortByDesc('total_sesiones');

                $stats['total_uso_libre'] = \App\Models\Asistencia::where('tipo', 'Uso Libre')
                    ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                    ->count();

                foreach ($centros as $centro) {
                    $chartData['labels'][] = $centro->nombre_centro;
                    $chartData['clases'][] = \App\Models\Asistencia::where('centro_computo_id', $centro->id)->where('tipo', 'Clase')->presenciales()->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])->count();
                    $chartData['usoLibre'][] = \App\Models\Asistencia::where('centro_computo_id', $centro->id)->where('tipo', 'Uso Libre')->presenciales()->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])->count();
                }

                $registrosUsoLibre = \App\Models\Asistencia::with(['user', 'centroComputo'])
                    ->where('tipo', 'Uso Libre')
                    ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                    ->orderBy('fecha', 'desc')
                    ->get();

                // 1. Las asistencias de alumnos (ya cargadas arriba, para los docentes)
                $reporteAlumnos = $asistenciasAlumnos->groupBy('user_id');

                // 2. ALCANCE GLOBAL POR CARRERA (El que ya teníamos abajo)
                // Sólo quien de verdad vino: un alumno con puras faltas no usó el centro.
                $asistenciasPresenciales = $asistenciasAlumnos->filter(fn($a) => self::estuvoPresente($a));
                $alumnosUnicos = $asistenciasPresenciales->unique('user_id');
                $totalAlumnosUnicos = $alumnosUnicos->count();

                $reporteCarreras = $alumnosUnicos->groupBy(function ($a) {
                    return $a->user->carrera ?? 'Sin asignar';
                })->map(function ($registros, $carrera) use ($totalAlumnosUnicos) {
                    $cantidad = $registros->count();
                    return [
                        'carrera' => $carrera,
                        'cantidad' => $cantidad,
                        'porcentaje' => $totalAlumnosUnicos > 0 ? round(($cantidad / $totalAlumnosUnicos) * 100) : 0
                    ];
                })->sortByDesc('porcentaje')->values();

                // 3. REPORTE INDIVIDUAL DE LABORATORIOS (Inyectando carreras)
                $reporteLaboratorios = [];
                foreach ($centros as $centro) {
                    $asistenciasClase = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                        ->where('tipo', 'Clase')
                        ->presenciales()
                        ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                        ->count();

                    $usosLibres = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                        ->where('tipo', 'Uso Libre')
                        ->presenciales()
                        ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                        ->count();

                    // De las asistencias a clase, cuántas fueron con la laptop del alumno
                    $conEquipoPersonal = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                        ->where('tipo', 'Clase')
                        ->where('equipo_personal', true)
                        ->presenciales()
                        ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                        ->count();

                    $horaPico = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                        ->presenciales()
                        ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                        ->selectRaw('HOUR(fecha_hora_registro) as hora, count(*) as total')
                        ->groupBy('hora')->orderBy('total', 'desc')->first();

                    $topMaquinas = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                        ->presenciales()
                        ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                        ->whereNotNull('numero_maquina')
                        ->selectRaw('numero_maquina, count(*) as total_usos')
                        ->groupBy('numero_maquina')
                        ->orderBy('total_usos', 'desc')
                        ->take(3)
                        ->get();

                    // --- NUEVO: TOP CARRERAS POR ESTE LABORATORIO ESPECÍFICO ---
                    $alumnosLab = $asistenciasPresenciales->where('centro_computo_id', $centro->id)->unique('user_id');
                    $totalAlumnosLab = $alumnosLab->count();

                    $topCarrerasLab = $alumnosLab->groupBy(function ($a) {
                        return $a->user->carrera ?? 'Sin asignar';
                    })->map(function ($registros, $carrera) use ($totalAlumnosLab) {
                        $cantidad = $registros->count();
                        return [
                            'carrera' => $carrera,
                            'cantidad' => $cantidad,
                            'porcentaje' => $totalAlumnosLab > 0 ? round(($cantidad / $totalAlumnosLab) * 100) : 0
                        ];
                    })->sortByDesc('cantidad')->take(3)->values(); // Tomamos solo el top 3 para la tarjeta
                    // -------------------------------------------------------------

                    $reporteLaboratorios[] = [
                        'id' => $centro->id,
                        'nombre' => $centro->nombre_centro,
                        'sesiones' => $asistenciasClase,
                        'uso_libre' => $usosLibres,
                        'equipo_personal' => $conEquipoPersonal,
                        'total_impacto' => $asistenciasClase + $usosLibres,
                        'hora_pico' => $horaPico ? $horaPico->hora . ':00 hrs' : 'N/D',
                        'top_maquinas' => $topMaquinas,
                        'top_carreras' => $topCarrerasLab // Empacamos las carreras
                    ];
                }
            }
        }

        $equiposDesgaste = \App\Models\Equipo::with('centroComputo')->orderBy('usos_acumulados', 'desc')->take(10)->get();

        $topMaquinasHistorico = collect();
        if ($semestreSeleccionadoId && $semestre) {
            $topMaquinasHistorico = \App\Models\Asistencia::with('centroComputo')
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->whereNotNull('numero_maquina')->whereNotNull('fecha_hora_salida')
                ->selectRaw('centro_computo_id, numero_maquina, count(*) as total_usos, SUM(TIMESTAMPDIFF(MINUTE, fecha_hora_registro, fecha_hora_salida)) as total_minutos')
                ->groupBy('centro_computo_id', 'numero_maquina')->orderBy('total_usos', 'desc')->take(10)->get();
        }

        return view('admin.reportes.index', compact(
            'semestres',
            'semestreSeleccionadoId',
            'semestreActivo',
            'centros',
            'materias',
            'grupos',
            'profesores',
            'stats',
            'chartData',
            'clasesDelSemestre',
            'asignaturasDelSemestre',
            'docentesActivos',
            'registrosUsoLibre',
            'reporteDocentes',
            'reporteAlumnos',
            'reporteLaboratorios',
            'equiposDesgaste',
            'topMaquinasHistorico',
            'asistenciasDocentes',
            'reporteMaterias',
            'reporteCarreras'
        ));
    }

    /**
     * Reúne todo lo que muestra la pestaña "Resumen General" para poder exportarlo.
     * No recalcula nada por su cuenta: se apoya en los mismos métodos que ya
     * alimentan los reportes de Docentes y de Laboratorios, y sólo añade las
     * cifras clave del encabezado.
     */
    private function obtenerDatosResumen(Request $request)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $semestreId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = Semestre::find($semestreId);

        if (!$semestre) {
            return null;
        }

        // Reutilizamos los cálculos ya probados de las otras pestañas
        $datosDocentes = $this->obtenerDatosDocentes($request);
        $datosLaboratorios = $this->obtenerDatosLaboratorios($request);

        $reporteDocentes = collect($datosDocentes['reporteDocentes'] ?? []);

        // El uso por laboratorio sale del mismo método que alimenta la pestaña
        // "Laboratorios y Uso Libre", de modo que las dos pantallas y sus
        // descargas reportan exactamente las mismas cifras.
        $reporteLaboratorios = collect($datosLaboratorios['reporteLaboratorios'] ?? []);

        // --- Cifras clave del encabezado (mismo criterio que la vista) ---
        $clasesDelSemestre = \App\Models\Horario::where('semestre_id', $semestre->id)->get();
        $clasesAgendadas = self::cifrasDeClasesAgendadas($clasesDelSemestre);

        $accesosClase = \App\Models\Asistencia::where('tipo', 'Clase')
            ->presenciales()
            ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
            ->count();

        $accesosUsoLibre = \App\Models\Asistencia::where('tipo', 'Uso Libre')
            ->presenciales()
            ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
            ->count();

        // Cumplimiento global ponderado por las clases que ya debieron impartirse
        $esperadasHoy = $reporteDocentes->sum('esperadas_hoy');
        $clasesCumplidas = $reporteDocentes->sum(fn($d) => ($d['presentes'] ?? 0) + ($d['justificadas'] ?? 0));
        $cumplimiento = $esperadasHoy > 0 ? min(100, round(($clasesCumplidas / $esperadasHoy) * 100)) : null;

        // Docentes por debajo del 85%, igual que en la tarjeta de la pantalla
        $docentesAtencion = $reporteDocentes
            ->filter(fn($d) => ($d['esperadas_hoy'] ?? 0) > 0 && ($d['porcentaje'] ?? 100) < 85)
            ->sortBy('porcentaje')
            ->values();

        return [
            'semestre' => $semestre,
            'accesosClase' => $accesosClase,
            'accesosUsoLibre' => $accesosUsoLibre,
            'totalAccesos' => $accesosClase + $accesosUsoLibre,
            'totalClases' => $clasesAgendadas['total'],
            'clasesFijas' => $clasesAgendadas['fijas'],
            'clasesEspeciales' => $clasesAgendadas['especiales'],
            'sesionesPorSemana' => $clasesAgendadas['sesiones_por_semana'],
            'docentesActivos' => $clasesDelSemestre->pluck('user_id')->unique()->filter()->count(),
            'docentesFijos' => $clasesDelSemestre->where('tipo_reserva', 'recurrente')->pluck('user_id')->unique()->count(),
            'docentesEspeciales' => $clasesDelSemestre->where('tipo_reserva', 'especial')->pluck('user_id')->unique()->count(),
            'esperadasHoy' => $esperadasHoy,
            'clasesCumplidas' => $clasesCumplidas,
            'cumplimiento' => $cumplimiento,
            'faltasTotales' => $reporteDocentes->sum('faltas'),
            'reporteLaboratorios' => $reporteLaboratorios->sortByDesc('total_impacto')->values(),
            'reporteCarreras' => collect($datosLaboratorios['reporteCarreras'] ?? []),
            'equiposDesgaste' => collect($datosLaboratorios['equiposDesgaste'] ?? [])->take(10),
            'docentesAtencion' => $docentesAtencion,
        ];
    }

    public function descargarResumenPdf(Request $request)
    {
        $datos = $this->obtenerDatosResumen($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el resumen general.');
        }

        $pdf = Pdf::loadView('admin.reportes.pdf.resumen_general', $datos)->setPaper('letter', 'portrait');
        return $pdf->download('Reporte_Resumen_General.pdf');
    }

    public function descargarResumenExcel(Request $request)
    {
        $datos = $this->obtenerDatosResumen($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el resumen general.');
        }

        return Excel::download(new \App\Exports\ResumenGeneralExport($datos), 'Reporte_Resumen_General.xlsx');
    }

    private function obtenerDatosDocentes($request)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = Semestre::find($semestreSeleccionadoId);

        // Sin un periodo válido no hay nada que reportar: se avisa en lugar de
        // reventar al leer las fechas de un semestre inexistente.
        if (!$semestre) {
            return null;
        }

        $clases = \App\Models\Horario::with(['materia', 'user', 'centroComputo'])
            ->where('semestre_id', $semestreSeleccionadoId)
            ->get();

        $asistenciasDocentes = \App\Models\AsistenciaProfesor::whereIn('horario_id', $clases->pluck('id'))->get();

        // --- NUEVO: Traemos a los alumnos para el Fallback ---
        $asistenciasAlumnos = \App\Models\Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $clases->pluck('id'))
            ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
            ->get();

        $diasInhabilesData = \App\Models\DiaInhabil::where('semestre_id', $semestre->id)->get();
        $diasInhabiles = $diasInhabilesData->pluck('fecha')->toArray();
        $motivosInhabiles = $diasInhabilesData->pluck('motivo', 'fecha')->toArray();

        $reporteDocentesData = $clases->groupBy('user_id')->map(function ($horarios) use ($semestre, $asistenciasDocentes, $asistenciasAlumnos, $diasInhabiles, $motivosInhabiles) {
            $profesor = $horarios->first()->user;
            $materiasUnicas = $horarios->pluck('materia.nombre_materia')->unique();

            $esperadas_total = 0;
            $esperadas_hoy = 0;
            $hoy = now()->startOfDay();

            $presentes_total = 0;
            $faltas_total = 0;
            $justificadas_total = 0;
            $por_registrar_total = 0;

            foreach ($horarios as $horario) {
                // Clases esperadas desde que la clase existe en el sistema
                $esperadas = self::clasesEsperadas(
                    $horario,
                    $semestre,
                    $diasInhabiles,
                    $asistenciasDocentes->where('horario_id', $horario->id)->pluck('fecha')
                        ->merge($asistenciasAlumnos->where('horario_id', $horario->id)->pluck('fecha'))
                );
                $esperadas_total += $esperadas['total'];
                $esperadas_hoy += $esperadas['hoy'];
                $fechasFestivasMateria = $esperadas['festivas'];

                // CÁLCULO DE ASISTENCIAS CON FALLBACK
                $asistProf = $asistenciasDocentes->where('horario_id', $horario->id);
                $asistAlum = $asistenciasAlumnos->where('horario_id', $horario->id);

                $imp = $asistProf->where('estado', 'asistio')->count();
                $fal = $asistProf->where('estado', 'falta')->count();
                $jus = $asistProf->where('estado', 'justificado')->count();

                $usaFallback = ($asistProf->count() == 0);
                if ($usaFallback) {
                    $fechasUnicas = $asistProf->pluck('fecha')
                        ->merge($asistAlum->pluck('fecha'))
                        ->merge($fechasFestivasMateria)
                        ->unique();

                    $imp = collect($fechasUnicas)->reject(function ($fecha) use ($motivosInhabiles) {
                        return array_key_exists($fecha, $motivosInhabiles);
                    })->count();
                }

                $presentes_total += $imp;
                $faltas_total += $fal;
                $justificadas_total += $jus;

                // Las que ya debieron darse y no tienen el registro del profesor: bajan
                // el cumplimiento sin ser faltas. Mismo criterio que "Rendimiento por
                // asignatura" (antes aquí no se veían y el porcentaje bajo no se explicaba).
                $conProfesor = $asistProf->pluck('fecha')->map(fn($f) => CalendarioDeClases::soloFecha($f))->flip();
                $conAlumnos = $asistAlum->pluck('fecha')->map(fn($f) => CalendarioDeClases::soloFecha($f))->flip();
                $por_registrar_total += collect($esperadas['fechas_hoy'])
                    ->reject(fn($f) => isset($conProfesor[$f]) || ($usaFallback && isset($conAlumnos[$f])))
                    ->count();
            }

            $porcentaje = 0;
            if ($esperadas_hoy > 0) {
                $porcentaje = round((($presentes_total + $justificadas_total) / $esperadas_hoy) * 100);
                if ($porcentaje > 100) $porcentaje = 100;
            }

            return [
                'profesor' => trim(($profesor->name ?? '') . ' ' . ($profesor->apellido_paterno ?? '') . ' ' . ($profesor->apellido_materno ?? '')) ?: 'N/A',
                'username' => $profesor->username ?? 'Profesor',
                'materias' => $materiasUnicas,
                'esperadas_total' => $esperadas_total,
                'esperadas_hoy' => $esperadas_hoy,
                'presentes' => $presentes_total,
                'faltas' => $faltas_total,
                'justificadas' => $justificadas_total,
                'por_registrar' => $por_registrar_total,
                'porcentaje' => $porcentaje,
                'color' => $porcentaje >= 85 ? 'success' : ($porcentaje >= 70 ? 'warning' : 'danger')
            ];
        })->sortByDesc('porcentaje')->values();

        $reporteMaterias = $clases->groupBy('materia_id')->map(function ($c) {
            return [
                'nombre' => $c->first()->materia->nombre_materia ?? 'Desconocida',
                'total_sesiones' => $c->count(),
                'total_profesores' => $c->pluck('user_id')->unique()->count()
            ];
        })->sortByDesc('total_sesiones');

        return [
            'semestre' => $semestre,
            'reporteDocentes' => $reporteDocentesData,
            'asistenciasDocentes' => $asistenciasDocentes,
            'reporteMaterias' => $reporteMaterias
        ];
    }

    public function descargarDocentesPdf(Request $request)
    {
        $datos = $this->obtenerDatosDocentes($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        $pdf = Pdf::loadView('admin.reportes.pdf.docentes', $datos)->setPaper('letter', 'landscape');
        return $pdf->download('Reporte_Docentes_Materias.pdf');
    }

    public function descargarDocentesExcel(Request $request)
    {
        $datos = $this->obtenerDatosDocentes($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        return Excel::download(new \App\Exports\DocentesExport($datos), 'Reporte_Docentes_Materias.xlsx');
    }

    private function obtenerDatosAlumnos($request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreId);

        // Sin un periodo válido no hay nada que reportar: se avisa en lugar de
        // reventar al leer las fechas de un semestre inexistente.
        if (!$semestre) {
            return null;
        }

        $asistencias = \App\Models\Asistencia::with(['user', 'centroComputo'])
            ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
            ->whereHas('user', fn($q) => $q->where('rol', 'Alumno'))
            ->get();

        return ['semestre' => $semestre, 'reporteAlumnos' => $asistencias->groupBy('user_id')];
    }

    public function descargarAlumnosPdf(Request $request)
    {
        $datos = $this->obtenerDatosAlumnos($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        return Pdf::loadView('admin.reportes.pdf.alumnos', $datos)->setPaper('letter', 'landscape')->download('Reporte_Alumnos.pdf');
    }

    public function descargarAlumnosExcel(Request $request)
    {
        $datos = $this->obtenerDatosAlumnos($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        return Excel::download(new \App\Exports\AlumnosExport($datos['reporteAlumnos']), 'Reporte_Alumnos.xlsx');
    }

    private function obtenerDatosLaboratorios(Request $request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semId);

        // Sin un periodo válido no hay nada que reportar: se avisa en lugar de
        // reventar al leer las fechas de un semestre inexistente.
        if (!$semestre) {
            return null;
        }

        $centros = \App\Models\CentroComputo::all();
        $clases = \App\Models\Horario::where('semestre_id', $semId)->get();

        $asistenciasAlumnos = collect();
        $reporteCarreras = collect();

        if ($semestre) {
            // Sólo quien de verdad vino: un alumno con puras faltas no usó el centro.
            $asistenciasAlumnos = \App\Models\Asistencia::with('user')
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->whereHas('user', fn($q) => $q->where('rol', 'Alumno'))
                ->get();

            $alumnosUnicos = $asistenciasAlumnos->unique('user_id');
            $totalAlumnosUnicos = $alumnosUnicos->count();

            $reporteCarreras = $alumnosUnicos->groupBy(function ($a) {
                return $a->user->carrera ?? 'Sin asignar';
            })->map(function ($registros, $carrera) use ($totalAlumnosUnicos) {
                $cantidad = $registros->count();
                return [
                    'carrera' => $carrera,
                    'cantidad' => $cantidad,
                    'porcentaje' => $totalAlumnosUnicos > 0 ? round(($cantidad / $totalAlumnosUnicos) * 100) : 0
                ];
            })->sortByDesc('porcentaje')->values();
        }

        $reporte = [];
        foreach ($centros as $centro) {
            // 'sesiones' cuenta los ACCESOS de alumnos en clase, no las clases programadas
            // del horario. Antes se contaban los horarios, y eso hacía dos cosas mal:
            // el reporte descargado no cuadraba con lo que muestra la pantalla de esta
            // misma pestaña, y el "impacto total" sumaba clases con personas.
            $sesiones = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                ->where('tipo', 'Clase')
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])->count();

            $libres = \App\Models\Asistencia::where('centro_computo_id', $centro->id)->where('tipo', 'Uso Libre')
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])->count();

            // De las asistencias a clase, cuántas fueron con la laptop del alumno
            $conEquipoPersonal = \App\Models\Asistencia::where('centro_computo_id', $centro->id)->where('tipo', 'Clase')
                ->where('equipo_personal', true)
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])->count();

            $horaPico = \App\Models\Asistencia::where('centro_computo_id', $centro->id)
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->selectRaw('HOUR(fecha_hora_registro) as hora, count(*) as total')
                ->groupBy('hora')->orderBy('total', 'desc')->first();

            $alumnosLab = $asistenciasAlumnos->where('centro_computo_id', $centro->id)->unique('user_id');
            $totalAlumnosLab = $alumnosLab->count();

            $topCarrerasLab = $alumnosLab->groupBy(function ($a) {
                return $a->user->carrera ?? 'Sin asignar';
            })->map(function ($registros, $carrera) use ($totalAlumnosLab) {
                $cantidad = $registros->count();
                return [
                    'carrera' => $carrera,
                    'cantidad' => $cantidad,
                    'porcentaje' => $totalAlumnosLab > 0 ? round(($cantidad / $totalAlumnosLab) * 100) : 0
                ];
            })->sortByDesc('cantidad')->take(3)->values();

            $reporte[] = [
                'nombre' => $centro->nombre_centro,
                'sesiones' => $sesiones,
                'uso_libre' => $libres,
                'equipo_personal' => $conEquipoPersonal,
                'total_impacto' => $sesiones + $libres,
                'hora_pico' => $horaPico ? $horaPico->hora . ':00 hrs' : 'N/D',
                'top_carreras' => $topCarrerasLab
            ];
        }

        $equiposDesgaste = \App\Models\Equipo::with('centroComputo')
            ->orderBy('usos_acumulados', 'desc')
            ->take(10)
            ->get();

        $topMaquinasHistorico = collect();
        if ($semestre) {
            $topMaquinasHistorico = \App\Models\Asistencia::with('centroComputo')
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->whereNotNull('numero_maquina')
                ->whereNotNull('fecha_hora_salida')
                ->selectRaw('centro_computo_id, numero_maquina, count(*) as total_usos, SUM(TIMESTAMPDIFF(MINUTE, fecha_hora_registro, fecha_hora_salida)) as total_minutos')
                ->groupBy('centro_computo_id', 'numero_maquina')
                ->orderBy('total_usos', 'desc')
                ->get();
        }

        return [
            'semestre' => $semestre,
            'reporteLaboratorios' => $reporte,
            'equiposDesgaste' => $equiposDesgaste,
            'topMaquinasHistorico' => $topMaquinasHistorico,
            'reporteCarreras' => $reporteCarreras
        ];
    }

    public function descargarLaboratoriosPdf(Request $request)
    {
        $datos = $this->obtenerDatosLaboratorios($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        return Pdf::loadView('admin.reportes.pdf.laboratorios', $datos)->download('Reporte_Infraestructura.pdf');
    }

    public function descargarLaboratoriosExcel(Request $request)
    {
        $datos = $this->obtenerDatosLaboratorios($request);

        if (!$datos) {
            return back()->with('error', 'Selecciona un periodo antes de descargar el reporte.');
        }

        return Excel::download(new \App\Exports\LaboratoriosExport($datos), 'Reporte_Infraestructura.xlsx');
    }


    public function mantenimientoMasivo(Request $request)
    {
        // Validamos que nos envíen un arreglo de equipos
        $request->validate([
            'equipos' => 'required|array',
            'equipos.*' => 'exists:equipos,id'
        ]);

        // IMPORTANTE: Aquí cambié 'activo' por 'disponible'. 
        // Si en tu sistema usas otra palabra para las PC que funcionan, cámbiala aquí.
        \App\Models\Equipo::whereIn('id', $request->equipos)
            ->update([
                'usos_acumulados' => 0,
                'estado' => 'disponible',
                'ultimo_mantenimiento' => now()
            ]);

        return redirect()->back()->with('success', 'Mantenimiento registrado. Se reinició el odómetro a 0 en los equipos seleccionados.');
    }

    // Función privada que calcula los datos (así no repetimos código)
    private function obtenerDatosCarreras($request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreSeleccionadoId);
        $centros = \App\Models\CentroComputo::all();

        $reporteCarreras = collect();
        $reporteLaboratorios = [];
        $totalAlumnosUnicos = 0;

        if ($semestre) {
            // Sólo quien de verdad vino: un alumno con puras faltas no usó el centro.
            $asistenciasAlumnos = \App\Models\Asistencia::with('user')
                ->presenciales()
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->whereHas('user', fn($q) => $q->where('rol', 'Alumno'))
                ->get();

            $alumnosUnicos = $asistenciasAlumnos->unique('user_id');
            $totalAlumnosUnicos = $alumnosUnicos->count();

            // GLOBAL
            $reporteCarreras = $alumnosUnicos->groupBy(function ($a) {
                return $a->user->carrera ?? 'Sin asignar';
            })->map(function ($registros, $carrera) use ($totalAlumnosUnicos) {
                $cantidad = $registros->count();
                return [
                    'carrera' => $carrera,
                    'cantidad' => $cantidad,
                    'porcentaje' => $totalAlumnosUnicos > 0 ? round(($cantidad / $totalAlumnosUnicos) * 100, 1) : 0
                ];
            })->sortByDesc('cantidad')->values();

            // POR LABORATORIO
            foreach ($centros as $centro) {
                $alumnosLab = $asistenciasAlumnos->where('centro_computo_id', $centro->id)->unique('user_id');
                $totalAlumnosLab = $alumnosLab->count();

                $carrerasLab = $alumnosLab->groupBy(function ($a) {
                    return $a->user->carrera ?? 'Sin asignar';
                })->map(function ($registros, $carrera) use ($totalAlumnosLab) {
                    $cantidad = $registros->count();
                    return [
                        'carrera' => $carrera,
                        'cantidad' => $cantidad,
                        'porcentaje' => $totalAlumnosLab > 0 ? round(($cantidad / $totalAlumnosLab) * 100, 1) : 0
                    ];
                })->sortByDesc('cantidad')->values();

                $reporteLaboratorios[] = [
                    'nombre' => $centro->nombre_centro,
                    'total_alumnos' => $totalAlumnosLab,
                    'carreras' => $carrerasLab
                ];
            }
        }

        return compact('semestre', 'semestreSeleccionadoId', 'reporteCarreras', 'reporteLaboratorios', 'totalAlumnosUnicos');
    }

    // 1. Muestra la vista en pantalla
    public function reporteCarrerasDetalle(Request $request)
    {
        $datos = $this->obtenerDatosCarreras($request);
        return view('admin.reportes.carreras_detalle', $datos);
    }

    // 2. Descarga el PDF
    public function descargarCarrerasDetallePdf(Request $request)
    {
        $datos = $this->obtenerDatosCarreras($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.carreras_detalle', $datos);
        return $pdf->download('Reporte_Detallado_Carreras.pdf');
    }

    // 3. Descarga el Excel
    public function descargarCarrerasDetalleExcel(Request $request)
    {
        $datos = $this->obtenerDatosCarreras($request);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CarrerasDetalleExport($datos), 'Reporte_Detallado_Carreras.xlsx');
    }

    private function obtenerDatosRecordHistorico(Request $request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreSeleccionadoId);

        $recordGlobal = collect();
        $recordLaboratorios = collect();

        if ($semestre) {
            // 1. REPORTE GLOBAL (Sin límite de 10)
            $recordGlobal = \App\Models\Asistencia::with('centroComputo')
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
                ->whereNotNull('numero_maquina')
                ->whereNotNull('fecha_hora_salida')
                ->selectRaw('centro_computo_id, numero_maquina, count(*) as total_usos, SUM(TIMESTAMPDIFF(MINUTE, fecha_hora_registro, fecha_hora_salida)) as total_minutos')
                ->groupBy('centro_computo_id', 'numero_maquina')
                ->orderBy('total_usos', 'desc')
                ->get();

            // 2. AGRUPAR POR LABORATORIO
            $recordLaboratorios = $recordGlobal->groupBy(function ($item) {
                return $item->centroComputo->nombre_centro ?? 'Sin Asignar';
            });
        }

        return compact('semestre', 'semestreSeleccionadoId', 'recordGlobal', 'recordLaboratorios');
    }

    // --- 1. MUESTRA LA VISTA EN PANTALLA ---
    public function recordHistoricoDetalle(Request $request)
    {
        $datos = $this->obtenerDatosRecordHistorico($request);
        return view('admin.reportes.record_historico_detalle', $datos);
    }

    // --- 2. DESCARGA EL PDF ---
    public function descargarRecordHistoricoPdf(Request $request)
    {
        $datos = $this->obtenerDatosRecordHistorico($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.record_historico', $datos);
        return $pdf->download('Record_Historico_Equipos.pdf');
    }

    // --- 3. DESCARGA EL EXCEL ---
    public function descargarRecordHistoricoExcel(Request $request)
    {
        $datos = $this->obtenerDatosRecordHistorico($request);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RecordHistoricoExport($datos), 'Record_Historico_Equipos.xlsx');
    }

    // --- NUEVA FUNCIÓN PRIVADA PARA HARDWARE ---
    private function obtenerDatosHardware(Request $request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreSeleccionadoId);

        // 1. REPORTE GLOBAL (Sin límite de 10) - Ordenado por usos acumulados (los que más urgen de mtto)
        $equiposGlobal = \App\Models\Equipo::with('centroComputo')
            ->orderBy('usos_acumulados', 'desc')
            ->get();

        $totalEquipos = $equiposGlobal->count();

        // 2. AGRUPAR POR LABORATORIO
        $equiposLaboratorios = $equiposGlobal->groupBy(function ($item) {
            return $item->centroComputo->nombre_centro ?? 'Sin Asignar';
        });

        return compact('semestre', 'semestreSeleccionadoId', 'equiposGlobal', 'equiposLaboratorios', 'totalEquipos');
    }

    // --- 1. MUESTRA LA VISTA EN PANTALLA ---
    public function hardwareDetalle(Request $request)
    {
        $datos = $this->obtenerDatosHardware($request);
        return view('admin.reportes.hardware_detalle', $datos);
    }

    // --- 2. DESCARGA EL PDF DE HARDWARE ---
    public function descargarHardwareDetallePdf(Request $request)
    {
        $datos = $this->obtenerDatosHardware($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.hardware_detalle', $datos);
        return $pdf->download('Reporte_Registro_Hardware.pdf');
    }

    // --- 3. DESCARGA EL EXCEL DE HARDWARE ---
    public function descargarHardwareDetalleExcel(Request $request)
    {
        $datos = $this->obtenerDatosHardware($request);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\HardwareDetalleExport($datos), 'Reporte_Registro_Hardware.xlsx');
    }

    // --- FUNCIÓN PRIVADA PARA DATOS DE USO LIBRE ---
    private function obtenerDatosUsoLibre(Request $request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreSeleccionadoId);

        // 1. Recibimos las fechas del filtro
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $registros = collect();
        if ($semestre) {
            $query = \App\Models\Asistencia::with(['user', 'centroComputo'])
                ->where('tipo', 'Uso Libre')
                ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin]);

            // 2. Aplicamos el filtro si el usuario seleccionó fechas
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            } elseif ($fechaInicio) {
                $query->where('fecha', $fechaInicio); // Si solo pone inicio, busca ese día
            } elseif ($fechaFin) {
                $query->where('fecha', $fechaFin); // Si solo pone fin, busca ese día
            }

            $registros = $query->orderBy('fecha', 'desc')
                ->orderBy('fecha_hora_registro', 'desc')
                ->get();
        }

        // Pasamos las fechas a la vista para mantenerlas en los inputs y botones
        return compact('semestre', 'semestreSeleccionadoId', 'registros', 'fechaInicio', 'fechaFin');
    }

    public function usoLibreDetalle(Request $request)
    {
        $datos = $this->obtenerDatosUsoLibre($request);
        return view('admin.reportes.uso_libre_detalle', $datos);
    }

    // --- 1. DESCARGA EL PDF DE USO LIBRE ---
    public function descargarUsoLibrePdf(Request $request)
    {
        $datos = $this->obtenerDatosUsoLibre($request);

        // Personalizamos el nombre del archivo según las fechas
        $nombreArchivo = 'Bitacora_Uso_Libre';
        if ($datos['fechaInicio']) $nombreArchivo .= '_' . $datos['fechaInicio'];
        if ($datos['fechaFin'] && $datos['fechaFin'] != $datos['fechaInicio']) $nombreArchivo .= '_al_' . $datos['fechaFin'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.uso_libre_detalle', $datos)
            ->setPaper('letter', 'portrait'); // Tamaño carta, vertical

        return $pdf->download($nombreArchivo . '.pdf');
    }

    // --- 2. DESCARGA EL EXCEL DE USO LIBRE ---
    public function descargarUsoLibreExcel(Request $request)
    {
        $datos = $this->obtenerDatosUsoLibre($request);

        $nombreArchivo = 'Bitacora_Uso_Libre';
        if ($datos['fechaInicio']) $nombreArchivo .= '_' . $datos['fechaInicio'];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\UsoLibreDetalleExport($datos),
            $nombreArchivo . '.xlsx'
        );
    }

    // --- FUNCIÓN PRIVADA PARA DATOS DE CLASES/MATERIAS (VERSIÓN AUDITORÍA + DESGLOSE + DÍAS INHÁBILES) ---
    private function obtenerDatosMaterias(Request $request)
    {
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();
        $semestreSeleccionadoId = $request->input('semestre_id', $semestreActivo ? $semestreActivo->id : null);
        $semestre = \App\Models\Semestre::find($semestreSeleccionadoId);

        $fechaInicio = $request->input('fecha_inicio', $semestre ? $semestre->fecha_inicio : null);
        $fechaFin = $request->input('fecha_fin', $semestre ? $semestre->fecha_fin : null);

        $reporteMaterias = collect();

        if ($semestre && $fechaInicio && $fechaFin) {

            // --- Obtenemos los objetos completos para sacar la fecha y el MOTIVO ---
            $diasInhabilesData = \App\Models\DiaInhabil::where('semestre_id', $semestre->id)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->get();

            $diasInhabiles = $diasInhabilesData->pluck('fecha')->toArray();
            $motivosInhabiles = $diasInhabilesData->pluck('motivo', 'fecha')->toArray();

            $asistenciasAlumnos = \App\Models\Asistencia::where('tipo', 'Clase')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->get();

            $asistenciasProfesores = \App\Models\AsistenciaProfesor::whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->get();

            // --- LA SOLUCIÓN (OPCIÓN B): Traemos TODOS los horarios cuyos grupos pertenezcan al semestre actual ---
            $horarios = \App\Models\Horario::with(['materia', 'grupo', 'user', 'centroComputo'])
                ->whereHas('grupo', function ($query) use ($semestre) {
                    $query->where('semestre_id', $semestre->id);
                })
                ->get();

            $reporteMaterias = $horarios->map(function ($horario) use ($semestre, $asistenciasAlumnos, $asistenciasProfesores, $fechaInicio, $fechaFin, $diasInhabiles, $motivosInhabiles) {

                $asistProf = $asistenciasProfesores->where('horario_id', $horario->id);
                $asistAlum = $asistenciasAlumnos->where('horario_id', $horario->id);

                // --- CÁLCULO DE CLASES PROGRAMADAS (TOTAL VS HASTA HOY) ---
                // Dentro del rango del reporte, y desde que la clase existe en el sistema.
                $esperadas = CalendarioDeClases::esperadas(
                    $horario,
                    $semestre,
                    $diasInhabiles,
                    $asistProf->pluck('fecha')->merge($asistAlum->pluck('fecha')),
                    $fechaInicio,
                    $fechaFin
                );
                $esperadas_total = $esperadas['total'];
                $esperadas_hoy = $esperadas['hoy'];
                $fechasFestivasMateria = $esperadas['festivas'];
                // --- FIN DEL CÁLCULO ---

                $impartidas = $asistProf->where('estado', 'asistio')->count();
                $faltas = $asistProf->where('estado', 'falta')->count();
                $justificadas = $asistProf->where('estado', 'justificado')->count();

                $fechasUnicas = $asistProf->pluck('fecha')
                    ->merge($asistAlum->pluck('fecha'))
                    ->merge($fechasFestivasMateria)
                    ->unique()
                    ->sortDesc();

                $usaFallback = ($asistProf->count() == 0);
                if ($usaFallback) {
                    $impartidas = collect($fechasUnicas)->reject(function ($fecha) use ($motivosInhabiles) {
                        return array_key_exists($fecha, $motivosInhabiles);
                    })->count();
                }

                $desgloseDias = [];
                $totalAlumnosEnImpartidas = 0;

                foreach ($fechasUnicas as $fecha) {
                    $profDia = $asistProf->where('fecha', $fecha)->first();
                    $alumDia = $asistAlum->where('fecha', $fecha);

                    $esInhabil = array_key_exists($fecha, $motivosInhabiles);
                    $motivoInhabil = $esInhabil ? $motivosInhabiles[$fecha] : null;

                    $alumnosDelDia = $alumDia->whereIn('estado', ['presente', 'justificado', null])->unique('user_id')->count();

                    $estadoDocente = $profDia ? $profDia->estado : ($esInhabil ? 'Inhábil' : ($usaFallback && $fechasUnicas->count() > 0 ? 'asistio' : 'Sin registro'));

                    if ($estadoDocente === 'asistio') {
                        $totalAlumnosEnImpartidas += $alumnosDelDia;
                    }

                    $desgloseDias[] = [
                        'fecha' => $fecha,
                        'estado_docente' => $estadoDocente,
                        'es_inhabil' => $esInhabil,
                        'motivo_inhabil' => $motivoInhabil,
                        'entrada_docente' => $profDia ? $profDia->fecha_hora_registro : null,
                        'salida_docente' => $profDia ? $profDia->fecha_hora_salida : null,
                        'alumnos_asistentes' => $alumnosDelDia
                    ];
                }

                // ... (calculas $promedio y $alumnosUnicos)
                $promedio = $impartidas > 0 ? round($totalAlumnosEnImpartidas / $impartidas, 1) : 0;
                $alumnosUnicos = $asistAlum->pluck('user_id')->unique()->count();

                // --- CÁLCULO DE CUMPLIMIENTO POR MATERIA ---
                $porcentaje_cumplimiento = 0;
                if ($esperadas_hoy > 0) {
                    $porcentaje_cumplimiento = round((($impartidas + $justificadas) / $esperadas_hoy) * 100);
                    if ($porcentaje_cumplimiento > 100) $porcentaje_cumplimiento = 100;
                }

                $color_cumplimiento = $porcentaje_cumplimiento >= 85 ? 'success' : ($porcentaje_cumplimiento >= 70 ? 'warning' : 'danger');
                // --------------------------------------------------

                // El día y la hora de este horario, para distinguirlo al juntar los
                // días de una misma asignatura: "Lunes 07:00 AM a 09:00 AM".
                $etiquetaDia = ($horario->fecha_especial
                        ? \Carbon\Carbon::parse($horario->fecha_especial)->format('d/m/Y')
                        : $horario->dia_semana)
                    . ' ' . ($horario->hora_inicio ? \Carbon\Carbon::parse($horario->hora_inicio)->format('h:i A') : '--')
                    . ' a ' . ($horario->hora_fin ? \Carbon\Carbon::parse($horario->hora_fin)->format('h:i A') : '--');

                foreach ($desgloseDias as $i => $dia) {
                    $desgloseDias[$i]['horario'] = $etiquetaDia;
                }

                // --- CLASES POR REGISTRAR ---
                // Las que ya debieron darse y no tienen el registro del profesor. Si el
                // profesor no tiene ningún registro, el reporte da por impartidos los
                // días con alumnos (el respaldo de arriba), así que esos no quedan
                // pendientes. Son justo las que bajan el cumplimiento sin ser faltas.
                $conProfesor = $asistProf->pluck('fecha')->map(fn($f) => CalendarioDeClases::soloFecha($f))->flip();
                $conAlumnos = $asistAlum->pluck('fecha')->map(fn($f) => CalendarioDeClases::soloFecha($f))->flip();

                $fechasPendientes = collect($esperadas['fechas_hoy'])
                    ->reject(fn($f) => isset($conProfesor[$f]) || ($usaFallback && isset($conAlumnos[$f])))
                    ->values();

                $yaEnDesglose = collect($desgloseDias)->pluck('fecha')->map(fn($f) => CalendarioDeClases::soloFecha($f))->flip();

                foreach ($desgloseDias as $i => $dia) {
                    if ($fechasPendientes->contains(CalendarioDeClases::soloFecha($dia['fecha']))) {
                        $desgloseDias[$i]['estado_docente'] = 'pendiente';   // tiene alumnos, pero falta el profesor
                    }
                }

                foreach ($fechasPendientes as $fechaPendiente) {
                    if (isset($yaEnDesglose[$fechaPendiente])) {
                        continue;
                    }

                    $desgloseDias[] = [
                        'fecha' => $fechaPendiente,
                        'estado_docente' => 'pendiente',
                        'es_inhabil' => false,
                        'motivo_inhabil' => null,
                        'entrada_docente' => null,
                        'salida_docente' => null,
                        'alumnos_asistentes' => 0,
                        'horario' => $etiquetaDia,
                    ];
                }

                return [
                    // Para juntar después todos los días de una misma asignatura
                    '_clave' => $horario->claveDeAsignatura(),
                    '_alumnos' => $asistAlum->pluck('user_id')->unique()->values()->all(),
                    '_etiqueta_dia' => $etiquetaDia,
                    'materia' => $horario->materia->nombre_materia ?? 'Materia Eliminada',
                    'grupo' => $horario->grupo->nombre_grupo ?? 'N/A',
                    'docente' => trim(($horario->user->name ?? '') . ' ' . ($horario->user->apellido_paterno ?? '')) ?: 'Sin Docente',
                    'laboratorio' => $horario->centroComputo->nombre_centro ?? 'N/A',
                    'esperadas_total' => $esperadas_total,
                    'esperadas_hoy' => $esperadas_hoy,
                    'impartidas' => $impartidas,
                    'faltas' => $faltas,
                    'justificadas' => $justificadas,
                    'porcentaje_cumplimiento' => $porcentaje_cumplimiento, // NUEVO
                    'color_cumplimiento' => $color_cumplimiento,           // NUEVO
                    'asistencia_total' => $totalAlumnosEnImpartidas,
                    'asistencia_promedio' => $promedio,
                    'horario_id' => $horario->id,
                    'alumnos_unicos' => $alumnosUnicos,
                    'desglose_dias' => collect($desgloseDias),
                    'pendientes' => $fechasPendientes->count(),
                    'profesor_id' => $horario->user_id,
                    'horario_asignado' => ($horario->hora_inicio ? \Carbon\Carbon::parse($horario->hora_inicio)->format('h:i A') : '--') . ' a ' . ($horario->hora_fin ? \Carbon\Carbon::parse($horario->hora_fin)->format('h:i A') : '--'),
                    'es_especial' => $horario->tipo_reserva === 'especial',
                ];
            });

            // Una fila por ASIGNATURA, no por día de clase. En el sistema, una materia
            // que se da lunes y miércoles son dos horarios; antes salía dos veces
            // en el reporte, y parecía duplicada.
            $reporteMaterias = $reporteMaterias->groupBy('_clave')
                ->map(fn($dias) => $this->juntarDiasDeAsignatura($dias))
                ->sortByDesc('asistencia_promedio')
                ->values();
        }

        return compact('semestre', 'semestreSeleccionadoId', 'reporteMaterias', 'fechaInicio', 'fechaFin');
    }

    /**
     * Junta en una sola fila los horarios (días) de una misma asignatura: misma
     * materia, mismo grupo y mismo docente. Las cifras se suman y el cumplimiento
     * y el promedio se recalculan sobre el total, no se promedian los porcentajes.
     */
    private function juntarDiasDeAsignatura($dias): array
    {
        $primero = $dias->first();

        $esperadasHoy = $dias->sum('esperadas_hoy');
        $impartidas = $dias->sum('impartidas');
        $justificadas = $dias->sum('justificadas');
        $asistenciaTotal = $dias->sum('asistencia_total');

        $cumplimiento = $esperadasHoy > 0
            ? min(100, round((($impartidas + $justificadas) / $esperadasHoy) * 100))
            : 0;

        return array_merge($primero, [
            'laboratorio'             => $dias->pluck('laboratorio')->unique()->implode(', '),
            'esperadas_total'         => $dias->sum('esperadas_total'),
            'esperadas_hoy'           => $esperadasHoy,
            'impartidas'              => $impartidas,
            'faltas'                  => $dias->sum('faltas'),
            'justificadas'            => $justificadas,
            'pendientes'              => $dias->sum('pendientes'),
            'porcentaje_cumplimiento' => $cumplimiento,
            'color_cumplimiento'      => $cumplimiento >= 85 ? 'success' : ($cumplimiento >= 70 ? 'warning' : 'danger'),
            'asistencia_total'        => $asistenciaTotal,
            'asistencia_promedio'     => $impartidas > 0 ? round($asistenciaTotal / $impartidas, 1) : 0,
            'alumnos_unicos'          => collect($dias->pluck('_alumnos')->flatten())->unique()->count(),
            'desglose_dias'           => $dias->pluck('desglose_dias')->flatten(1)->sortByDesc('fecha')->values(),
            'horario_asignado'        => $dias->pluck('_etiqueta_dia')->implode(' · '),
            'dias_de_clase'           => $dias->count(),
        ]);
    }

    public function materiasDetalle(Request $request)
    {
        $datos = $this->obtenerDatosMaterias($request);
        return view('admin.reportes.materias_detalle', $datos);
    }

    // --- 1. DESCARGA EL PDF DE AUDITORÍA ACADÉMICA ---
    public function descargarMateriasPdf(Request $request)
    {
        $datos = $this->obtenerDatosMaterias($request);

        // Personalizamos el nombre del archivo según las fechas
        $nombreArchivo = 'Auditoria_Academica';
        if ($datos['fechaInicio']) $nombreArchivo .= '_' . $datos['fechaInicio'];
        if ($datos['fechaFin'] && $datos['fechaFin'] != $datos['fechaInicio']) $nombreArchivo .= '_al_' . $datos['fechaFin'];

        // Usamos 'landscape' (horizontal) porque son muchas columnas
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.materias_detalle', $datos)
            ->setPaper('letter', 'landscape');

        return $pdf->download($nombreArchivo . '.pdf');
    }

    // --- 2. DESCARGA EL EXCEL DE AUDITORÍA ACADÉMICA ---
    public function descargarMateriasExcel(Request $request)
    {
        $datos = $this->obtenerDatosMaterias($request);

        $nombreArchivo = 'Auditoria_Academica';
        if ($datos['fechaInicio']) $nombreArchivo .= '_' . $datos['fechaInicio'];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MateriasDetalleExport($datos),
            $nombreArchivo . '.xlsx'
        );
    }

    private function contarDiasClase($fechaInicio, $fechaFin, $diasSemana, $semestreId)
    {
        $inicio = \Carbon\Carbon::parse($fechaInicio);
        $fin = \Carbon\Carbon::parse($fechaFin);
        $count = 0;

        // Obtenemos los días inhábiles de este periodo para no contarlos
        $diasInhabiles = \App\Models\DiaInhabil::where('semestre_id', $semestreId)
            ->pluck('fecha')
            ->toArray();

        while ($inicio <= $fin) {
            // Verificamos si el día actual es uno de los días que tiene clase (ej. Lunes=1, Miércoles=3)
            // Y verificamos que NO sea un día inhábil
            if (in_array($inicio->dayOfWeekIso, $diasSemana) && !in_array($inicio->format('Y-m-d'), $diasInhabiles)) {
                $count++;
            }
            $inicio->addDay();
        }
        return $count;
    }
}
