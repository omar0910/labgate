@extends('layouts.admin')
@section('title', 'Análisis Académico por Materia')

@section('content')

    <div class="container-fluid py-4">

        {{-- 1. ENCABEZADO SUPERIOR --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-journal-check fs-1 text-marca-green me-3"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Análisis de Asistencia por Asignatura</h4>
                        <p class="text-muted small mb-0 mt-1">Periodo:
                            <strong>{{ $semestre->nombre ?? 'N/A' }}</strong> | Materias activas en laboratorios:
                            <strong>{{ $reporteMaterias->count() }}</strong>
                        </p>
                    </div>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.index', ['semestre_id' => $semestreSeleccionadoId]) }}#docentes"
                        class="btn btn-outline-secondary rounded-pill shadow-sm px-4" title="Volver a Reportes">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- 2. FILTROS DE RANGO SEMANAL O DIARIO --}}
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
            <div class="card-body p-4">
                <form action="{{ route('admin.reportes.materias-detalle') }}" method="GET">
                    <input type="hidden" name="semestre_id" value="{{ $semestreSeleccionadoId }}">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase"><i
                                    class="bi bi-calendar-event me-1"></i> Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" value="{{ $fechaInicio }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase"><i
                                    class="bi bi-calendar-event me-1"></i> Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" value="{{ $fechaFin }}">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit"
                                    class="btn btn-marca-black fw-bold flex-grow-1 shadow-sm rounded-3 py-2">
                                    <i class="bi bi-funnel-fill me-1"></i> Filtrar Auditoría
                                </button>

                                {{-- Botón para limpiar los filtros si hay alguno activo --}}
                                @if (request('fecha_inicio') || request('fecha_fin'))
                                    <a href="{{ route('admin.reportes.materias-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                                        class="btn btn-outline-danger shadow-sm rounded-3 py-2 px-3"
                                        title="Limpiar filtros">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- 3. TARJETA DE CONTROLES Y TABLA ANALÍTICA --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">

            {{-- Encabezado interno con botones de exportación --}}
            <div
                class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-marca-green me-2"></i> Rendimiento por
                        Asignatura</h5>
                    @if (request('fecha_inicio') || request('fecha_fin'))
                        <p class="text-marca-green small mb-0 mt-1 fw-bold">Mostrando resultados filtrados por fecha.</p>
                    @else
                        <p class="text-muted small mb-0 mt-1">Exporta la auditoría completa de este periodo.</p>
                    @endif
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.materias-detalle.pdf', ['semestre_id' => $semestreSeleccionadoId, 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}"
                        class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                    </a>
                    <a href="{{ route('admin.reportes.materias-detalle.excel', ['semestre_id' => $semestreSeleccionadoId, 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}"
                        class="btn bg-marca-green text-white rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
                    </a>
                </div>
            </div>

            {{-- Tabla con Scroll --}}
            <div class="card-body p-0">
                @include('partials.buscador-en-tabla', [
                    'tabla' => 'tablaAnalisisAsignaturas',
                    'etiqueta' => 'Buscar asignatura o docente',
                    'ayuda' => 'Materia, grupo, docente o laboratorio...',
                    'singular' => 'clase',
                    'plural' => 'clases',
                ])
                <div class="table-responsive" style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tablaAnalisisAsignaturas">
                        <thead class="table-light text-muted small text-uppercase sticky-top">
                            <tr>
                                <th class="ps-4 border-0 py-3">Asignatura / Grupo</th>
                                <th class="border-0">Docente / Laboratorio</th>
                                <th class="text-center border-0" title="Clases Programadas">Prog.<br><small>(Hoy /
                                        Semestre)</small></th>
                                <th class="text-center border-0" title="Clases Impartidas">Imp.</th>
                                <th class="text-center border-0" title="Faltas del Docente">Faltas</th>
                                <th class="text-center border-0" title="Faltas Justificadas">Justif.</th>
                                <th class="text-center border-0" title="Cumplimiento del Docente">Cump.</th>
                                <th class="text-center border-0">Alumnos <br><small>(Promedio / Grupo)</small></th>
                                <th class="text-center border-0 pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reporteMaterias as $item)
                                <tr
                                    data-buscar="{{ $item['materia'] }} {{ $item['grupo'] }} {{ $item['docente'] }} {{ $item['laboratorio'] }}">
                                    {{-- En la celda de la Asignatura --}}
                                    <td>
                                        <span class="fw-bold text-dark">{{ $item['materia'] }}</span>

                                        {{-- Agregamos este badge si es reserva especial --}}
                                        @if (isset($item['es_especial']) && $item['es_especial'])
                                            <span class="badge bg-info text-dark small" style="font-size: 0.6rem;">RESERVA
                                                ESPECIAL</span>
                                        @endif

                                        <br>
                                        <span style="color: #666;">Grupo: {{ $item['grupo'] }}</span>
                                    </td>
                                    <td class="border-bottom border-light">
                                        <div class="small fw-bold text-dark"><i class="bi bi-person-badge me-1"></i>
                                            {{ $item['docente'] }}</div>
                                        <div class="small text-marca-green fw-bold mt-1"><i class="bi bi-geo-alt me-1"></i>
                                            {{ $item['laboratorio'] }}</div>
                                    </td>
                                    <td class="text-center align-middle border-bottom border-light">
                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                            <div class="d-flex align-items-baseline gap-1">
                                                <h6 class="mb-0 fw-bold text-marca-green"
                                                    title="Clases programadas hasta hoy">
                                                    {{ $item['esperadas_hoy'] }}
                                                </h6>
                                                <span class="text-muted fs-6">/</span>
                                                <span class="fw-bold text-muted small"
                                                    title="Total de clases en el periodo">
                                                    {{ $item['esperadas_total'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center border-bottom border-light">
                                        <span class="fs-6 fw-bold text-success">{{ $item['impartidas'] }}</span>
                                    </td>
                                    <td class="text-center border-bottom border-light">
                                        <span
                                            class="badge {{ $item['faltas'] > 0 ? 'bg-danger text-white' : 'bg-light text-muted border' }} rounded-pill px-3 py-2">
                                            {{ $item['faltas'] }}
                                        </span>
                                    </td>
                                    <td class="text-center border-bottom border-light">
                                        <span
                                            class="badge {{ $item['justificadas'] > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border' }} rounded-pill px-3 py-2">
                                            {{ $item['justificadas'] }}
                                        </span>
                                    </td>
                                    <td class="text-center border-bottom border-light align-middle">
                                        @if ($item['esperadas_hoy'] > 0)
                                            <span class="fw-bold text-{{ $item['color_cumplimiento'] }}"
                                                style="font-size: 1.1rem;">
                                                {{ $item['porcentaje_cumplimiento'] }}%
                                            </span>
                                            {{-- Clases que ya pasaron y no tienen registro: bajan el
                                                 cumplimiento sin ser faltas --}}
                                            @if (($item['pendientes'] ?? 0) > 0)
                                                <div class="text-muted fw-bold text-nowrap" style="font-size: 0.7rem;"
                                                    title="Clases que ya debieron darse y no tienen registro del docente">
                                                    <i class="bi bi-hourglass-split"></i>
                                                    {{ $item['pendientes'] }} por registrar
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-muted small">N/A</span>
                                        @endif
                                    </td>
                                    <td class="text-center border-bottom border-light">
                                        <div class="d-inline-flex align-items-center bg-light px-3 py-1 rounded-pill fw-bold border border-secondary border-opacity-25"
                                            title="Promedio de asistencia vs Total de alumnos inscritos">
                                            <span class="text-success">{{ $item['asistencia_promedio'] }}</span>
                                            <span class="text-muted mx-1">/</span>
                                            <span class="text-dark">{{ $item['alumnos_unicos'] }}</span>
                                            <i class="bi bi-people-fill ms-2 text-muted"></i>
                                        </div>
                                    </td>
                                    <td class="text-center pe-4 border-bottom border-light">
                                        <button class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3 shadow-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetalle{{ $item['horario_id'] }}">
                                            <i class="bi bi-list-check me-1"></i> Detalle
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted border-0">
                                        <i class="bi bi-clipboard-x fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">No hay información académica</span>
                                        <small>No se encontraron datos de clases impartidas en las fechas
                                            seleccionadas.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- MODALES DE DESGLOSE POR DÍA --}}
    @foreach ($reporteMaterias as $item)
        <div class="modal fade" id="modalDetalle{{ $item['horario_id'] }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-4">

                    {{-- Header del Modal --}}
                    <div class="modal-header border-0 pb-0 ps-4 pt-4">
                        <div>
                            <h5 class="modal-title fw-bold text-dark"><i
                                    class="bi bi-journal-text text-marca-green me-2"></i> Pases de Lista</h5>
                            <p class="text-muted small mb-0 mt-1">Desglose cronológico de clases.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        {{-- Tarjeta de Info Resumen --}}
                        <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-4 border">
                            <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm me-3 border"
                                style="width: 50px; height: 50px;">
                                <i class="bi bi-book-half text-marca-green fs-3"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $item['materia'] }} <span
                                        class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill ms-1">{{ $item['grupo'] }}</span>
                                </h6>
                                <small class="text-muted d-block mt-1"><i class="bi bi-person-badge me-1"></i> Docente:
                                    <strong>{{ $item['docente'] }}</strong></small>
                            </div>
                        </div>

                        {{-- Aviso de clases sin registro, con acceso directo para capturarlas --}}
                        @if (($item['pendientes'] ?? 0) > 0)
                            <div
                                class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4 p-3 rounded-4 border border-warning bg-warning bg-opacity-10">
                                <div class="small text-dark">
                                    <i class="bi bi-hourglass-split text-dark me-1"></i>
                                    <strong>{{ $item['pendientes'] }}
                                        {{ $item['pendientes'] == 1 ? 'clase ya pasó y no tiene' : 'clases ya pasaron y no tienen' }}
                                        registro</strong> del docente. Aparecen abajo como "Por registrar".
                                </div>
                                <a href="{{ route('admin.bitacora.pendientes', ['profesor' => $item['profesor_id'], 'origen' => 'rendimiento', 'semestre_id' => $semestreSeleccionadoId]) }}"
                                    class="btn btn-sm btn-marca-black rounded-pill fw-bold px-3 text-nowrap">
                                    Registrar <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        @endif

                        {{-- Tabla Cronológica --}}
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0 small text-uppercase ps-3 py-2">Fecha</th>
                                        <th class="border-0 small text-uppercase py-2">Estado / Actividad</th>
                                        <th class="border-0 small text-uppercase text-center py-2 pe-3">Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($item['desglose_dias'] as $dia)
                                        {{-- Resaltamos la fila si es día inhábil --}}
                                        <tr
                                            class="border-bottom {{ $dia['es_inhabil'] ? 'bg-light bg-opacity-50' : '' }}">
                                            <td class="ps-3 py-2">
                                                <span
                                                    class="fw-bold text-dark">{{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</span>
                                                @if ($dia['es_inhabil'])
                                                    <br><small class="text-danger fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">
                                                        <i class="bi bi-calendar2-x-fill me-1"></i> Día Inhábil
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                @if ($dia['es_inhabil'])
                                                    {{-- Badge especial para Días Inhábiles --}}
                                                    <span class="badge bg-dark rounded-pill px-3 py-1 fw-bold shadow-sm"
                                                        style="font-size: 0.75rem;">
                                                        <i class="bi bi-star-fill text-warning me-1"></i> ASUETO:
                                                        {{ $dia['motivo_inhabil'] }}
                                                    </span>
                                                @else
                                                    {{-- Lógica normal de pases de lista --}}
                                                    @if ($dia['estado_docente'] == 'asistio')
                                                        <span
                                                            class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">Asistió</span>
                                                    @elseif($dia['estado_docente'] == 'falta')
                                                        <span
                                                            class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2">Falta</span>
                                                    @elseif($dia['estado_docente'] == 'justificado')
                                                        <span
                                                            class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 rounded-pill px-2">Justificado</span>
                                                    @elseif($dia['estado_docente'] == 'pendiente')
                                                        {{-- Ya pasó y nadie registró si el docente dio la clase --}}
                                                        <a href="{{ route('admin.bitacora.pendientes', ['profesor' => $item['profesor_id'], 'desde' => \Carbon\Carbon::parse($dia['fecha'])->format('Y-m-d'), 'hasta' => \Carbon\Carbon::parse($dia['fecha'])->format('Y-m-d'), 'origen' => 'rendimiento', 'semestre_id' => $semestreSeleccionadoId]) }}"
                                                            class="badge bg-marca-yellow text-marca-black rounded-pill px-2 text-decoration-none"
                                                            title="Registrar esta clase en Clases pendientes">
                                                            <i class="bi bi-hourglass-split me-1"></i>Por registrar
                                                        </a>
                                                    @else
                                                        <span
                                                            class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-2">Sin
                                                            Registro</span>
                                                    @endif
                                                @endif

                                                <small class="text-muted ms-2 fw-bold"
                                                    title="Horario oficial de la clase">
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ $dia['horario'] ?? $item['horario_asignado'] }}
                                                </small>
                                            </td>
                                            <td class="text-center py-2 pe-3">
                                                <div
                                                    class="d-inline-flex align-items-center {{ $dia['es_inhabil'] ? 'opacity-50' : 'bg-light' }} text-dark px-3 py-1 rounded-pill fw-bold border">
                                                    {{ $dia['alumnos_asistentes'] }} <i
                                                        class="bi bi-people-fill ms-2 text-muted"></i>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted small">No hay registros
                                                detallados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
