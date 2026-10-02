@extends('layouts.admin')
@section('title', 'Reporte Detallado de Carreras')

@section('content')
    <div class="container-fluid py-4">
        {{-- 2. ENCABEZADO CON ICONO VERDE Y BOTÓN DE VOLVER --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-mortarboard fs-1 text-success me-3" style="color: #009B4D !important;"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Demanda Académica Por Carreras</h4>
                        <p class="text-muted small mb-0 mt-1">Periodo:
                            <strong>{{ $semestre->nombre ?? 'Seleccione un semestre' }}</strong> | Total de Estudiantes
                            Únicos: <strong>{{ $totalAlumnosUnicos }}</strong>
                        </p>
                    </div>
                </div>
                {{-- BOTÓN DE VOLVER --}}
                <div class="mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.index', ['semestre_id' => $semestreSeleccionadoId]) }}#infraestructura"
                        class="btn btn-outline-secondary rounded-pill shadow-sm px-4" title="Volver a Reportes">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- 3. SECCIÓN GLOBAL Y BOTONES DE EXPORTACIÓN --}}
        <div class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-body p-4">

                {{-- Controles de la tabla y Exportación --}}
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-globe-americas me-2" style="color: #009B4D;"></i> Impacto Institucional (Global)
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Distribución total de alumnos únicos en todo el sistema.</p>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        {{-- Botones Activos con Rutas Reales --}}
                        <a href="{{ route('admin.reportes.carreras-detalle.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                        </a>
                        <a href="{{ route('admin.reportes.carreras-detalle.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-success rounded-pill fw-bold shadow-sm px-4"
                            style="background-color: #009B4D; border-color: #009B4D;">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
                        </a>
                    </div>
                </div>

                {{-- Tabla Global CON SCROLL INTERNO --}}
                <div class="table-responsive bg-white rounded-3 shadow-sm border p-0"
                    style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr class="text-uppercase text-muted small">
                                <th class="ps-4 py-3 border-0 rounded-start" style="width: 5%;">#</th>
                                <th class="border-0" style="width: 45%;">Programa Educativo (Carrera)</th>
                                <th class="text-center border-0" style="width: 25%;">Estudiantes Únicos</th>
                                <th class="text-center border-0 rounded-end" style="width: 25%;">Porcentaje del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reporteCarreras as $index => $dato)
                                <tr class="border-bottom">
                                    <td class="ps-4 py-3 text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $dato['carrera'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge bg-light text-dark border border-secondary border-opacity-25 rounded-pill px-3 py-2 fs-6">
                                            {{ $dato['cantidad'] }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <span class="fw-bold me-2">{{ $dato['porcentaje'] }}%</span>
                                            <div class="progress shadow-sm flex-grow-1"
                                                style="height: 6px; max-width: 100px;">
                                                <div class="progress-bar"
                                                    style="width: {{ $dato['porcentaje'] }}%; background-color: #009B4D;">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-clipboard-data fs-1 opacity-25 d-block mb-3"></i>
                                        Aún no hay registros de estudiantes para este periodo.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 4. DESGLOSE INDIVIDUAL POR LABORATORIO --}}
        <h5 class="fw-bold text-dark mb-4 px-2">
            <i class="bi bi-pc-display-horizontal me-2" style="color: #009B4D;"></i> Desglose Detallado por Laboratorio
        </h5>

        <div class="row g-4 mb-4">
            @foreach ($reporteLaboratorios ?? [] as $lab)
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        {{-- Encabezado verde tipo tarjeta de laboratorio --}}
                        <div class="card-header text-white border-0 py-3 px-4" style="background-color: #009B4D;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-pc-display me-2"></i> {{ $lab['nombre'] }}</h6>
                                <span class="badge bg-white text-dark rounded-pill shadow-sm">
                                    {{ $lab['total_alumnos'] }} Alumnos
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-0 bg-white">
                            {{-- SCROLL INTERNO PARA MANTENER LA SIMETRÍA DE LAS TARJETAS --}}
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.70rem;">
                                            <th class="ps-4 py-2 border-0">Carrera</th>
                                            <th class="text-center py-2 border-0">Cant.</th>
                                            <th class="text-center py-2 pe-4 border-0">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lab['carreras'] as $carrera)
                                            <tr class="border-bottom">
                                                <td class="ps-4 py-3 fw-bold text-dark small"
                                                    style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                    title="{{ $carrera['carrera'] }}">
                                                    {{ $carrera['carrera'] }}
                                                </td>
                                                <td class="text-center py-3">
                                                    <span
                                                        class="badge bg-light text-dark border border-secondary border-opacity-25 rounded-pill px-2">
                                                        {{ $carrera['cantidad'] }}
                                                    </span>
                                                </td>
                                                <td class="text-center py-3 pe-4">
                                                    <div class="d-flex align-items-center justify-content-center">
                                                        <span
                                                            class="small fw-bold me-2">{{ $carrera['porcentaje'] }}%</span>
                                                        <div class="progress" style="height: 4px; width: 40px;">
                                                            <div class="progress-bar"
                                                                style="width: {{ $carrera['porcentaje'] }}%; background-color: #009B4D;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted small">
                                                    Sin registros únicos.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
@endsection
