@extends('layouts.admin')
@section('title', 'Récord Histórico Detallado de Equipos')

@section('content')
    <div class="container-fluid py-4">

        {{-- 2. ENCABEZADO CON ICONO Y BOTÓN DE VOLVER --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-award-fill fs-1 text-warning me-3"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Carga de Trabajo por Computadora</h4>
                        <p class="text-muted small mb-0 mt-1">Periodo:
                            <strong>{{ $semestre->nombre ?? 'Seleccione un semestre' }}</strong> | Listado sin límite de
                            registros.
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
                            <i class="bi bi-globe-americas me-2" style="color: #009B4D;"></i> Ranking Institucional (Global)
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Equipos con mayor cantidad de sesiones en todos los
                            laboratorios.</p>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        <a href="{{ route('admin.reportes.record-historico.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                        </a>
                        <a href="{{ route('admin.reportes.record-historico.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-success rounded-pill fw-bold shadow-sm px-4"
                            style="background-color: #009B4D; border-color: #009B4D;">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
                        </a>
                    </div>
                </div>

                {{-- Tabla Global --}}
                <div class="table-responsive bg-white rounded-3 shadow-sm border p-0"
                    style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr class="text-uppercase text-muted small">
                                <th class="ps-4 py-3 border-0 rounded-start" style="width: 5%;">#</th>
                                <th class="border-0" style="width: 45%;">Equipo / Ubicación</th>
                                <th class="text-center border-0" style="width: 25%;">Total Sesiones</th>
                                <th class="text-center border-0 rounded-end" style="width: 25%;">Horas de Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recordGlobal as $index => $hist)
                                @php
                                    $horas = floor($hist->total_minutos / 60);
                                    $minutos = $hist->total_minutos % 60;
                                @endphp
                                <tr class="border-bottom">
                                    <td class="ps-4 py-3 text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-bold text-dark">PC #{{ $hist->numero_maquina }}</span>
                                        <div class="small text-muted">{{ $hist->centroComputo->nombre_centro ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge bg-success bg-opacity-10 text-marca-green border border-marca-green rounded-pill px-3 py-2 fs-6">
                                            {{ $hist->total_usos }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center px-3 py-1">
                                            <i class="bi bi-clock-fill text-warning me-1"></i>
                                            <span class="fw-bold text-dark fs-5">{{ $horas }}h
                                                {{ $minutos }}m</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-pc-display fs-1 opacity-25 d-block mb-3"></i>
                                        Aún no hay registros de uso en este periodo.
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
            @foreach ($recordLaboratorios as $nombreLab => $equiposLab)
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        {{-- Encabezado verde institucional --}}
                        <div class="card-header text-white border-0 py-3 px-4" style="background-color: #009B4D;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-door-open me-2"></i> {{ $nombreLab }}</h6>
                            </div>
                        </div>

                        <div class="card-body p-0 bg-white">
                            {{-- SCROLL INTERNO PARA MANTENER LA SIMETRÍA DE LAS TARJETAS --}}
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    {{-- STICKY TOP PARA QUE NO SE PIERDAN LOS ENCABEZADOS --}}
                                    <thead class="table-light sticky-top">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.70rem;">
                                            <th class="ps-4 py-2 border-0">Equipo</th>
                                            <th class="text-center py-2 border-0">Sesiones</th>
                                            <th class="text-center py-2 pe-4 border-0">Tiempo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($equiposLab as $hist)
                                            @php
                                                $horas = floor($hist->total_minutos / 60);
                                                $minutos = $hist->total_minutos % 60;
                                            @endphp
                                            <tr class="border-bottom">
                                                <td class="ps-4 py-3 fw-bold text-dark small">
                                                    PC #{{ $hist->numero_maquina }}
                                                </td>
                                                <td class="text-center py-3">
                                                    <span
                                                        class="badge bg-white text-marca-green border border-marca-green border-opacity-50 rounded-pill px-2 shadow-sm">
                                                        {{ $hist->total_usos }}
                                                    </span>
                                                </td>
                                                <td class="text-center py-3 pe-4">
                                                    <span class="small fw-bold text-dark">
                                                        <i class="bi bi-clock-fill text-warning me-1"></i>
                                                        {{ $horas }}h {{ $minutos }}m
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted small">
                                                    Sin registros.
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
