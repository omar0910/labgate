@php
    // ==========================================================
    // Cálculos del resumen. Todo sale de datos que el controlador
    // ya trae; no se consulta nada nuevo a la base.
    // ==========================================================

    $asistClases = array_sum($chartData['clases'] ?? []);
    $asistUsoLibre = array_sum($chartData['usoLibre'] ?? []);
    $totalAccesos = $asistClases + $asistUsoLibre;

    // Cumplimiento docente global, ponderado por las clases que ya debieron
    // impartirse a la fecha (no por las del semestre completo).
    $docentes = collect($reporteDocentes ?? []);
    $esperadasHoy = $docentes->sum('esperadas_hoy');
    $clasesCumplidas = $docentes->sum(fn($d) => ($d['presentes'] ?? 0) + ($d['justificadas'] ?? 0));
    $cumplimiento = $esperadasHoy > 0 ? min(100, round(($clasesCumplidas / $esperadasHoy) * 100)) : null;
    $faltasTotales = $docentes->sum('faltas');

    // Laboratorios ordenados por uso total
    $labs = collect($reporteLaboratorios ?? [])->sortByDesc('total_impacto')->values();
    $maxImpacto = max(1, (int) $labs->max('total_impacto'));

    // Carreras atendidas
    $carreras = collect($reporteCarreras ?? []);
    $maxCarrera = max(1, (int) $carreras->max('cantidad'));

    // Docentes por debajo del 85% de cumplimiento.
    // Se ordena de menor a mayor porcentaje antes de recortar: la lista llega
    // ordenada de mayor a menor, y sin este sortBy el take(5) dejaba fuera
    // justamente a los docentes con peor cumplimiento.
    $docentesAtencion = $docentes
        ->filter(fn($d) => ($d['esperadas_hoy'] ?? 0) > 0 && ($d['porcentaje'] ?? 100) < 85)
        ->sortBy('porcentaje')
        ->take(5);

    $equipos = collect($equiposDesgaste ?? [])->take(5);
    $maxUsos = max(1, (int) $equipos->max('usos_acumulados'));

    $hayDatos = $semestreSeleccionadoId && ($totalAccesos > 0 || ($stats['total_clases'] ?? 0) > 0);
@endphp

{{-- ============================================================ --}}
{{-- SIN PERIODO SELECCIONADO --}}
{{-- ============================================================ --}}
@if (!$semestreSeleccionadoId)
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body text-center py-5 px-4">
            <i class="bi bi-calendar3 fs-1 text-secondary opacity-50 d-block mb-3"></i>
            <h5 class="fw-bold text-marca-black mb-1">Selecciona un periodo</h5>
            <p class="text-muted small mb-0">
                Elige un semestre en el filtro de arriba para generar el reporte general.
            </p>
        </div>
    </div>
@else

    {{-- ============================================================ --}}
    {{-- 1. CONTEXTO DEL REPORTE --}}
    {{-- ============================================================ --}}
    <div class="resumen-contexto d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="resumen-contexto-icono">
                <i class="bi bi-clipboard-data-fill"></i>
            </div>
            <div>
                <h5 class="fw-bold text-marca-black mb-1">Reporte general del centro de cómputo</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @php $semSel = $semestres->firstWhere('id', $semestreSeleccionadoId); @endphp
                    <span class="badge rounded-pill bg-marca-green">
                        <i class="bi bi-calendar-range me-1"></i>
                        {{ $semSel->nombre ?? 'Periodo seleccionado' }}
                    </span>
                    @if ($semSel && $semSel->es_activo)
                        <span class="badge rounded-pill bg-marca-yellow">Periodo actual</span>
                    @endif
                    <span class="text-muted small">
                        Corte al {{ \Carbon\Carbon::now()->translatedFormat('d \d\e F \d\e Y') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.reportes.resumen.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                <i class="bi bi-file-earmark-pdf-fill"></i> PDF
            </a>
            <a href="{{ route('admin.reportes.resumen.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                class="btn btn-success rounded-pill fw-bold shadow-sm px-4" style="background-color: #198754;">
                <i class="bi bi-file-earmark-excel-fill"></i> Excel
            </a>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- 2. CIFRAS CLAVE --}}
    {{-- ============================================================ --}}
    <div class="row g-3 mb-4">

        {{-- Cifra principal: accesos totales --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 tarjeta-hero">
                <div class="card-body p-4">
                    <span class="etiqueta-kpi">Accesos registrados en el periodo</span>

                    <div class="cifra-hero">{{ number_format($totalAccesos) }}</div>

                    <p class="text-muted small mb-3">Entradas al centro, sumando clases y uso libre.</p>

                    {{-- Desglose: parte-todo en una sola barra apilada --}}
                    @php
                        $pctClases = $totalAccesos > 0 ? ($asistClases / $totalAccesos) * 100 : 0;
                        $pctUso = $totalAccesos > 0 ? ($asistUsoLibre / $totalAccesos) * 100 : 0;
                    @endphp
                    <div class="barra-desglose mb-3" role="img"
                        aria-label="{{ $asistClases }} accesos por clase y {{ $asistUsoLibre }} por uso libre">
                        <span class="segmento segmento-clases" style="width: {{ $pctClases }}%"></span>
                        <span class="segmento segmento-uso" style="width: {{ $pctUso }}%"></span>
                    </div>

                    <div class="d-flex flex-wrap gap-4">
                        <div>
                            <span class="clave-serie clave-clases"></span>
                            <span class="text-muted small">Clases</span>
                            <div class="fw-bold text-marca-black">{{ number_format($asistClases) }}</div>
                        </div>
                        <div>
                            <span class="clave-serie clave-uso"></span>
                            <span class="text-muted small">Uso libre</span>
                            <div class="fw-bold text-marca-black">{{ number_format($asistUsoLibre) }}</div>
                        </div>
                    </div>

                    <button class="btn btn-sm btn-enlace-kpi mt-3" data-bs-toggle="modal"
                        data-bs-target="#modalUsoLibre">
                        Ver historial de uso libre <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- Tres indicadores de apoyo --}}
        <div class="col-lg-7">
            <div class="row g-3 h-100">

                {{-- Clases agendadas --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 tarjeta-kpi">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="etiqueta-kpi">Clases agendadas</span>
                            {{-- Asignaturas (materia + grupo + docente), no horarios: una
                                 materia de lunes y miércoles cuenta una vez. --}}
                            <div class="cifra-kpi">{{ number_format($stats['total_clases']) }}</div>
                            <div class="text-muted small mt-2 lh-lg">
                                <div>
                                    <strong class="text-marca-black">{{ $stats['clases_fijas'] }}</strong>
                                    {{ $stats['clases_fijas'] == 1 ? 'recurrente' : 'recurrentes' }}
                                    @if (($stats['sesiones_por_semana'] ?? 0) > 0)
                                        <span class="d-block" style="font-size: .75rem;">
                                            {{ $stats['sesiones_por_semana'] }}
                                            {{ $stats['sesiones_por_semana'] == 1 ? 'sesión' : 'sesiones' }} por semana
                                        </span>
                                    @endif
                                </div>
                                <div>
                                    <strong class="text-marca-black">{{ $stats['clases_especiales'] }}</strong>
                                    {{ $stats['clases_especiales'] == 1 ? 'especial' : 'especiales' }}
                                </div>
                            </div>
                            <button class="btn btn-sm btn-enlace-kpi mt-auto" data-bs-toggle="modal"
                                data-bs-target="#modalClases">
                                Ver detalle <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Docentes activos --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 tarjeta-kpi">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="etiqueta-kpi">Docentes activos</span>
                            <div class="cifra-kpi">{{ number_format($stats['docentes_activos']) }}</div>
                            <div class="text-muted small mt-2 lh-lg">
                                <div><strong class="text-marca-black">{{ $stats['docentes_fijos'] }}</strong> con clase fija
                                </div>
                                <div><strong class="text-marca-black">{{ $stats['docentes_especiales'] }}</strong> con
                                    reserva</div>
                            </div>
                            <button class="btn btn-sm btn-enlace-kpi mt-auto" data-bs-toggle="modal"
                                data-bs-target="#modalDocentes">
                                Ver detalle <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Cumplimiento docente --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 tarjeta-kpi">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="etiqueta-kpi">Cumplimiento de clases</span>

                            @if (is_null($cumplimiento))
                                <div class="cifra-kpi text-muted">—</div>
                                <p class="text-muted small mt-2 mb-0">Aún no hay clases que debieran haberse impartido.</p>
                            @else
                                @php
                                    $estado =
                                        $cumplimiento >= 85 ? 'bien' : ($cumplimiento >= 70 ? 'aviso' : 'critico');
                                    $estadoTexto = [
                                        'bien' => 'En meta',
                                        'aviso' => 'Por debajo de la meta',
                                        'critico' => 'Requiere atención',
                                    ][$estado];
                                    $estadoIcono = [
                                        'bien' => 'bi-check-circle-fill',
                                        'aviso' => 'bi-exclamation-triangle-fill',
                                        'critico' => 'bi-x-octagon-fill',
                                    ][$estado];
                                @endphp

                                <div class="cifra-kpi">{{ $cumplimiento }}<span class="unidad">%</span></div>

                                <div class="medidor medidor-{{ $estado }} mt-2">
                                    <span style="width: {{ $cumplimiento }}%"></span>
                                </div>

                                <div class="estado-{{ $estado }} small fw-bold mt-2">
                                    <i class="bi {{ $estadoIcono }} me-1"></i>{{ $estadoTexto }}
                                </div>

                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                    {{ number_format($clasesCumplidas) }} de {{ number_format($esperadasHoy) }} clases
                                    impartidas
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- 3. LABORATORIOS Y CARRERAS --}}
    {{-- ============================================================ --}}
    <div class="row g-4 mb-4">

        {{-- Uso por laboratorio: el largo compara laboratorios entre sí y
             los colores muestran de qué se compone cada uno. --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <h6 class="fw-bold text-marca-black text-uppercase mb-0" style="letter-spacing: .5px;">
                            <i class="bi bi-pc-display text-marca-green me-2"></i>Uso por laboratorio
                        </h6>
                        <div class="d-flex gap-3 align-items-center">
                            <span class="d-flex align-items-center">
                                <span class="clave-serie clave-clases"></span>
                                <span class="text-muted small">Clases</span>
                            </span>
                            <span class="d-flex align-items-center">
                                <span class="clave-serie clave-uso"></span>
                                <span class="text-muted small">Uso libre</span>
                            </span>
                        </div>
                    </div>
                    <p class="text-muted small mb-4">De mayor a menor actividad, con su hora de mayor demanda.</p>

                    @forelse ($labs as $lab)
                        @php
                            $totLab = (int) $lab['total_impacto'];
                            $anchoLab = ($totLab / $maxImpacto) * 100;
                            $pctClasesLab = $totLab > 0 ? ($lab['sesiones'] / $totLab) * 100 : 0;
                            $pctUsoLab = $totLab > 0 ? ($lab['uso_libre'] / $totLab) * 100 : 0;
                        @endphp
                        <div class="fila-ranking">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-bold text-marca-black small">{{ $lab['nombre'] }}</span>
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-clock me-1"></i>Hora pico {{ $lab['hora_pico'] }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="pista-barra flex-grow-1">
                                    <span class="barra-apilada" style="width: {{ $anchoLab }}%">
                                        <span class="segmento-clases" style="width: {{ $pctClasesLab }}%"></span>
                                        <span class="segmento-uso" style="width: {{ $pctUsoLab }}%"></span>
                                    </span>
                                </div>
                                <span class="valor-barra">{{ number_format($totLab) }}</span>
                            </div>
                            @if ($totLab > 0)
                                <div class="text-muted mt-1" style="font-size: .72rem;">
                                    {{ number_format($lab['sesiones']) }} en clase ·
                                    {{ number_format($lab['uso_libre']) }} en uso libre
                                </div>
                            @else
                                <div class="text-muted mt-1" style="font-size: .72rem;">Sin actividad registrada</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No hay laboratorios con actividad en este periodo.</p>
                    @endforelse

                    @if ($labs->count() > 0)
                        <div class="mt-3">
                            <button class="btn btn-sm btn-link text-decoration-none text-muted fw-bold p-0"
                                type="button" data-bs-toggle="collapse" data-bs-target="#tablaLaboratorios">
                                <i class="bi bi-table me-1"></i> Ver los datos en tabla
                            </button>
                            <div class="collapse" id="tablaLaboratorios">
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm align-middle mb-0 tabla-datos">
                                        <thead>
                                            <tr>
                                                <th>Laboratorio</th>
                                                <th class="text-end">Clases</th>
                                                <th class="text-end">Uso libre</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($labs as $lab)
                                                <tr>
                                                    <td class="fw-bold text-marca-black">{{ $lab['nombre'] }}</td>
                                                    <td class="text-end">{{ number_format($lab['sesiones']) }}</td>
                                                    <td class="text-end">{{ number_format($lab['uso_libre']) }}</td>
                                                    <td class="text-end fw-bold">
                                                        {{ number_format($lab['total_impacto']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Carreras atendidas --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h6 class="fw-bold text-marca-black text-uppercase mb-1" style="letter-spacing: .5px;">
                        <i class="bi bi-mortarboard-fill text-marca-green me-2"></i>Carreras atendidas
                    </h6>
                    <p class="text-muted small mb-4">Alumnos distintos que usaron el centro, por carrera.</p>

                    @forelse ($carreras->take(6) as $c)
                        <div class="fila-ranking">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-bold text-marca-black small">{{ $c['carrera'] }}</span>
                                <span class="valor-barra">{{ $c['cantidad'] }}</span>
                            </div>
                            <div class="pista-barra">
                                <span style="width: {{ ($c['cantidad'] / $maxCarrera) * 100 }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin alumnos registrados en este periodo.</p>
                    @endforelse

                    @if ($carreras->count() > 0)
                        <a href="{{ route('admin.reportes.carreras-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-sm btn-enlace-kpi mt-auto align-self-start">
                            Ver reporte por carrera <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- 4. PUNTOS QUE REQUIEREN ATENCIÓN --}}
    {{-- ============================================================ --}}
    <div class="row g-4 mb-4">

        {{-- Docentes por debajo de la meta --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold text-marca-black text-uppercase mb-0" style="letter-spacing: .5px;">
                            <i class="bi bi-person-exclamation text-marca-green me-2"></i>Cumplimiento por docente
                        </h6>
                        @if ($faltasTotales > 0)
                            <span class="badge rounded-pill estado-critico-badge">
                                {{ $faltasTotales }} {{ $faltasTotales == 1 ? 'falta' : 'faltas' }}
                            </span>
                        @endif
                    </div>
                    <p class="text-muted small mb-4">Docentes por debajo del 85% de clases impartidas.</p>

                    @forelse ($docentesAtencion as $d)
                        @php $est = $d['porcentaje'] >= 70 ? 'aviso' : 'critico'; @endphp
                        <div class="fila-ranking">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-bold text-marca-black small">{{ $d['profesor'] }}</span>
                                <span class="valor-barra estado-{{ $est }}">{{ $d['porcentaje'] }}%</span>
                            </div>
                            <div class="medidor medidor-{{ $est }}">
                                <span style="width: {{ $d['porcentaje'] }}%"></span>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                {{ $d['presentes'] }} {{ $d['presentes'] == 1 ? 'impartida' : 'impartidas' }} ·
                                {{ $d['faltas'] }} {{ $d['faltas'] == 1 ? 'falta' : 'faltas' }} ·
                                {{ $d['justificadas'] }}
                                {{ $d['justificadas'] == 1 ? 'justificada' : 'justificadas' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check fs-2 text-marca-green d-block mb-2"></i>
                            <span class="fw-bold text-marca-black d-block">Todos en meta</span>
                            <small class="text-muted">Ningún docente está por debajo del 85%.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Equipos con más desgaste --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h6 class="fw-bold text-marca-black text-uppercase mb-1" style="letter-spacing: .5px;">
                        <i class="bi bi-tools text-marca-green me-2"></i>Equipos con más uso
                    </h6>
                    <p class="text-muted small mb-4">Candidatos a mantenimiento preventivo.</p>

                    @forelse ($equipos as $eq)
                        <div class="fila-ranking">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-bold text-marca-black small">
                                    PC #{{ $eq->numero_maquina }}
                                    <span class="text-muted fw-normal">·
                                        {{ $eq->centroComputo->nombre_centro ?? 'Sin laboratorio' }}</span>
                                </span>
                                <span class="valor-barra">{{ number_format($eq->usos_acumulados) }}</span>
                            </div>
                            <div class="pista-barra">
                                <span style="width: {{ ($eq->usos_acumulados / $maxUsos) * 100 }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin equipos registrados.</p>
                    @endforelse

                    @if ($equipos->count() > 0)
                        <a href="{{ route('admin.reportes.hardware-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-sm btn-enlace-kpi mt-auto align-self-start">
                            Ver reporte de equipos <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- 5. REPORTES DETALLADOS --}}
    {{-- ============================================================ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-2">
        <div class="card-body p-4">
            <h6 class="fw-bold text-marca-black text-uppercase mb-1" style="letter-spacing: .5px;">
                <i class="bi bi-folder2-open text-marca-green me-2"></i>Reportes detallados
            </h6>
            <p class="text-muted small mb-4">Cada uno se puede consultar en pantalla y exportar a PDF o Excel.</p>

            @php
                $enlaces = [
                    [
                        'ruta' => 'admin.reportes.materias-detalle',
                        'icono' => 'bi-journal-bookmark-fill',
                        'titulo' => 'Materias',
                        'texto' => 'Sesiones y docentes por materia',
                    ],
                    [
                        'ruta' => 'admin.reportes.carreras-detalle',
                        'icono' => 'bi-mortarboard-fill',
                        'titulo' => 'Carreras',
                        'texto' => 'Alcance por programa educativo',
                    ],
                    [
                        'ruta' => 'admin.reportes.uso-libre-detalle',
                        'icono' => 'bi-person-workspace',
                        'titulo' => 'Uso libre',
                        'texto' => 'Historial de accesos individuales',
                    ],
                    [
                        'ruta' => 'admin.reportes.hardware-detalle',
                        'icono' => 'bi-pc-display',
                        'titulo' => 'Equipos',
                        'texto' => 'Desgaste y mantenimiento',
                    ],
                    [
                        'ruta' => 'admin.reportes.record-historico',
                        'icono' => 'bi-clock-history',
                        'titulo' => 'Récord histórico',
                        'texto' => 'Máquinas con más horas de uso',
                    ],
                    [
                        'ruta' => 'admin.reportes.clases-hoy',
                        'icono' => 'bi-calendar-check',
                        'titulo' => 'Clases de hoy',
                        'texto' => 'Agenda del día en curso',
                        // Para que su "Volver" regrese aquí y no al Inicio
                        'extra' => ['origen' => 'reportes', 'pestana' => 'dashboard'],
                    ],
                ];
            @endphp

            <div class="row g-3">
                @foreach ($enlaces as $e)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route($e['ruta'], ['semestre_id' => $semestreSeleccionadoId] + ($e['extra'] ?? [])) }}"
                            class="tarjeta-enlace">
                            <span class="tarjeta-enlace-icono"><i class="bi {{ $e['icono'] }}"></i></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-bold text-marca-black small">{{ $e['titulo'] }}</span>
                                <span class="d-block text-muted" style="font-size: 0.75rem;">{{ $e['texto'] }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- MODALES DE DETALLE --}}
    {{-- ============================================================ --}}

    {{-- Clases agendadas --}}
    <div class="modal fade" id="modalClases" tabindex="-1" aria-labelledby="modalClasesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-marca-green text-white rounded-top-4 border-0">
                    <h5 class="modal-title fw-bold" id="modalClasesLabel">
                        <i class="bi bi-calendar-week-fill me-2"></i> Detalle de clases agendadas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4">Materia / Grupo</th>
                                    <th>Docente</th>
                                    <th>Días y horario</th>
                                    <th>Laboratorio</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Una fila por asignatura, con todos sus días juntos --}}
                                @forelse($asignaturasDelSemestre ?? [] as $clase)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ $clase->materia->nombre_materia ?? 'N/A' }}</div>
                                            <small class="text-muted">Grupo:
                                                {{ $clase->grupo->nombre_grupo ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            {{ trim(($clase->user->name ?? '') . ' ' . ($clase->user->apellido_paterno ?? '') . ' ' . ($clase->user->apellido_materno ?? '')) ?: 'N/A' }}
                                        </td>
                                        <td class="small text-nowrap">
                                            @foreach ($clase->sesiones as $sesion)
                                                <div>{{ $sesion }}</div>
                                            @endforeach
                                        </td>
                                        <td>{{ $clase->laboratorios ?: 'N/A' }}</td>
                                        <td>
                                            @if ($clase->tipo_reserva == 'recurrente')
                                                <span class="badge bg-success rounded-pill">Recurrente</span>
                                            @else
                                                <span class="badge bg-primary rounded-pill">Especial</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No hay clases agendadas en este semestre.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Docentes activos --}}
    <div class="modal fade" id="modalDocentes" tabindex="-1" aria-labelledby="modalDocentesLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-marca-green text-white rounded-top-4 border-0">
                    <h5 class="modal-title fw-bold" id="modalDocentesLabel">
                        <i class="bi bi-person-video3 me-2"></i> Docentes activos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($docentesActivos as $docente)
                            <li class="list-group-item d-flex align-items-center py-3 px-4">
                                <div class="tarjeta-enlace-icono me-3">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $docente->name }} {{ $docente->apellido_paterno }}
                                        {{ $docente->apellido_materno }}</div>
                                    <small class="text-muted">{{ $docente->username ?? 'Profesor' }}</small>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center py-4 text-muted">
                                No hay docentes registrados en este semestre.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Historial de uso libre --}}
    <div class="modal fade" id="modalUsoLibre" tabindex="-1" aria-labelledby="modalUsoLibreLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white rounded-top-4 border-0" style="background-color: #2A5CA8;">
                    <h5 class="modal-title fw-bold" id="modalUsoLibreLabel">
                        <i class="bi bi-person-workspace me-2"></i> Historial de uso libre del semestre
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4">Alumno</th>
                                    <th>Laboratorio</th>
                                    <th>Fecha</th>
                                    <th class="text-center">PC</th>
                                    <th>Entrada / Salida</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($registrosUsoLibre as $registro)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ $registro->user->name ?? 'N/A' }}</div>
                                            <small class="text-muted">{{ $registro->user->matricula ?? 'S/M' }}</small>
                                        </td>
                                        <td>{{ $registro->centroComputo->nombre_centro ?? 'N/A' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                {{ $registro->equipo_personal ? 'Equipo personal' : ($registro->numero_maquina ? '#' . $registro->numero_maquina : '—') }}
                                            </span>
                                        </td>
                                        <td>
                                            <small class="d-block text-success">
                                                <i class="bi bi-box-arrow-in-right"></i>
                                                {{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('H:i') }}
                                            </small>
                                            @if ($registro->fecha_hora_salida)
                                                <small class="d-block text-danger">
                                                    <i class="bi bi-box-arrow-left"></i>
                                                    {{ \Carbon\Carbon::parse($registro->fecha_hora_salida)->format('H:i') }}
                                                </small>
                                            @else
                                                <span class="badge rounded-pill estado-critico-badge"
                                                    style="font-size: .7rem;">En curso</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No hay registros de uso libre en este periodo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-4">
                    <a href="{{ route('admin.reportes.uso-libre', ['origen' => 'reportes', 'pestana' => 'dashboard', 'semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-marca-green rounded-pill fw-bold px-4 shadow-sm">
                        <i class="bi bi-search me-1"></i> Ir al buscador avanzado
                    </a>
                </div>
            </div>
        </div>
    </div>

@endif

{{-- ============================================================ --}}
{{-- ESTILOS DEL RESUMEN --}}
{{-- ============================================================ --}}
<style>
    /* --- Encabezado de contexto --- */
    .resumen-contexto-icono {
        width: 52px;
        height: 52px;
        border-radius: 1rem;
        background-color: rgba(0, 155, 77, .1);
        color: var(--marca-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    /* --- Cifras --- */
    .etiqueta-kpi {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #6c757d;
        margin-bottom: .35rem;
    }

    /* Cifra principal de la vista. Sin tabular-nums: a este tamaño los
       dígitos de ancho fijo se ven separados. */
    .cifra-hero {
        font-size: 3.5rem;
        font-weight: 700;
        line-height: 1;
        color: var(--marca-black);
        margin-bottom: .5rem;
    }

    .cifra-kpi {
        font-size: 2.1rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--marca-black);
    }

    .cifra-kpi .unidad {
        font-size: 1.1rem;
        font-weight: 600;
        color: #6c757d;
        margin-left: 2px;
    }

    .tarjeta-hero {
        border-top: 4px solid var(--marca-green) !important;
    }

    .tarjeta-kpi {
        transition: box-shadow .2s ease;
    }

    .tarjeta-kpi:hover {
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08) !important;
    }

    /* --- Claves de color de las series --- */
    .clave-serie {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 3px;
        margin-right: 6px;
        vertical-align: middle;
        flex-shrink: 0;
    }

    .clave-clases {
        background-color: var(--marca-serie-clases);
    }

    .clave-uso {
        background-color: var(--marca-serie-uso-libre);
    }

    /* --- Barra de desglose (parte-todo) --- */
    .barra-desglose {
        display: flex;
        height: 10px;
        border-radius: 5px;
        overflow: hidden;
        background-color: #eef1f4;
    }

    /* El separador entre segmentos es un hueco del color de la superficie,
       no un borde: el borde añadiría tinta que no es dato. */
    .barra-desglose .segmento+.segmento {
        margin-left: 2px;
    }

    .segmento-clases {
        background-color: var(--marca-serie-clases);
    }

    .segmento-uso {
        background-color: var(--marca-serie-uso-libre);
    }

    /* --- Barras de ranking --- */
    .fila-ranking {
        margin-bottom: 1.1rem;
    }

    .fila-ranking:last-of-type {
        margin-bottom: 0;
    }

    .pista-barra {
        height: 8px;
        border-radius: 4px;
        background-color: #eef1f4;
        overflow: hidden;
    }

    .pista-barra>span {
        display: block;
        height: 100%;
        border-radius: 4px;
        background-color: var(--marca-green);
    }

    .valor-barra {
        font-size: .85rem;
        font-weight: 700;
        color: var(--marca-black);
        font-variant-numeric: tabular-nums;
        min-width: 42px;
        text-align: right;
    }

    /* --- Medidor de cumplimiento: la pista es un tono claro del mismo color
           del relleno, para que el estado se lea en toda la barra --- */
    .medidor {
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
    }

    .medidor>span {
        display: block;
        height: 100%;
        border-radius: 4px;
    }

    .medidor-bien {
        background-color: rgba(0, 155, 77, .15);
    }

    .medidor-bien>span {
        background-color: #009B4D;
    }

    .medidor-aviso {
        background-color: rgba(180, 120, 0, .15);
    }

    .medidor-aviso>span {
        background-color: #B47800;
    }

    .medidor-critico {
        background-color: rgba(193, 42, 42, .15);
    }

    .medidor-critico>span {
        background-color: #C12A2A;
    }

    /* Los colores de estado siempre van acompañados de icono o texto,
       nunca comunican solos. */
    .estado-bien {
        color: #007a3d;
    }

    .estado-aviso {
        color: #8A5B00;
    }

    .estado-critico {
        color: #A81F1F;
    }

    .estado-critico-badge {
        background-color: rgba(193, 42, 42, .1);
        color: #A81F1F;
        border: 1px solid rgba(193, 42, 42, .25);
        font-weight: 700;
    }

    /* --- Barra apilada del ranking de laboratorios ---
       El ancho total compara un laboratorio contra el que más uso tiene;
       dentro, los dos segmentos muestran de qué se compone ese uso. */
    /* Es hija de .pista-barra, que le impone "display: block" y relleno
       verde con mayor especificidad; hay que anular ambos aquí, si no los
       segmentos se apilan uno debajo del otro y el color de adentro no se ve. */
    .pista-barra>span.barra-apilada {
        display: flex;
        background-color: transparent;
        height: 100%;
        border-radius: 4px;
        overflow: hidden;
        min-width: 0;
    }

    .barra-apilada>span {
        display: block;
        height: 100%;
    }

    /* La separación entre segmentos es un hueco del color de la superficie */
    .barra-apilada>span+span {
        margin-left: 2px;
    }

    .tabla-datos thead th {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #6c757d;
        border-bottom: 1px solid #dee2e6;
        font-weight: 700;
    }

    .tabla-datos td {
        font-size: .85rem;
        font-variant-numeric: tabular-nums;
        border-bottom: 1px solid #f1f3f5;
    }

    /* --- Enlaces --- */
    .btn-enlace-kpi {
        color: var(--marca-green);
        font-weight: 700;
        font-size: .78rem;
        padding: .35rem 0 0 0;
        border: none;
        background: none;
        text-align: left;
    }

    .btn-enlace-kpi:hover {
        color: var(--marca-dark-green);
    }

    .tarjeta-enlace {
        display: flex;
        align-items: center;
        gap: .85rem;
        padding: .9rem 1rem;
        border: 1px solid #e9ecef;
        border-radius: .85rem;
        text-decoration: none;
        background-color: #fff;
        height: 100%;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .tarjeta-enlace:hover {
        border-color: var(--marca-green);
        box-shadow: 0 .35rem .9rem rgba(0, 0, 0, .06);
        transform: translateY(-2px);
    }

    .tarjeta-enlace-icono {
        width: 38px;
        height: 38px;
        border-radius: .6rem;
        background-color: #f4f6f9;
        color: var(--marca-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    /* --- Impresión --- */
    @media print {

        .tarjeta-enlace,
        .btn-enlace-kpi {
            display: none !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
            break-inside: avoid;
        }
    }
</style>
