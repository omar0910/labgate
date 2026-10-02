@extends('layouts.admin')
@section('title', 'Registro Físico Detallado de Hardware')

@section('content')
    <div class="container-fluid py-4">

        {{-- 2. ENCABEZADO CON ICONO VERDE Y BOTÓN DE VOLVER --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-activity fs-1 text-success me-3" style="color: #009B4D !important;"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Desgaste y Mantenimiento de Equipos</h4>
                        <p class="text-muted small mb-0 mt-1">Periodo:
                            <strong>{{ $semestre->nombre ?? 'Seleccione un periodo' }}</strong> | Total de Equipos:
                            <strong>{{ $totalEquipos }}</strong>
                        </p>
                    </div>
                </div>
                {{-- BOTÓN DE VOLVER (Ya programado para regresar a la pestaña de infraestructura) --}}
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
                            <i class="bi bi-globe-americas me-2" style="color: #009B4D;"></i> Inventario Institucional
                            (Global)
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Lista completa de equipos ordenados por mayor necesidad de
                            mantenimiento (Usos recientes).</p>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        {{-- Botones listos pero desactivados hasta el próximo paso --}}
                        <a href="{{ route('admin.reportes.hardware-detalle.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                            class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                        </a>
                        <a href="{{ route('admin.reportes.hardware-detalle.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
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
                                <th class="border-0" style="width: 30%;">Equipo / Ubicación</th>
                                <th class="text-center border-0" style="width: 20%;">Usos Recientes</th>
                                <th class="text-center border-0" style="width: 20%;">Histórico Total</th>
                                <th class="text-center border-0" style="width: 15%;">Últ. Mtto</th>
                                <th class="text-center border-0 rounded-end" style="width: 10%;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equiposGlobal as $index => $equipo)
                                <tr class="border-bottom">
                                    <td class="ps-4 py-3 text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-bold text-dark fs-6">PC #{{ $equipo->numero_maquina }}</span>
                                        <div class="small text-muted"><i
                                                class="bi bi-geo-alt-fill opacity-50 me-1"></i>{{ $equipo->centroComputo->nombre_centro ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark fs-5">{{ $equipo->usos_acumulados }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="text-muted bg-light rounded-pill d-inline-block px-3 py-1 border"
                                            style="font-size: 0.85rem;">
                                            <i class="bi bi-speedometer2 me-1 opacity-75"></i>
                                            <strong>{{ $equipo->usos_historicos }}</strong>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if ($equipo->ultimo_mantenimiento)
                                            <div class="small text-marca-green fw-bold">
                                                <i class="bi bi-calendar-check"></i>
                                                {{ \Carbon\Carbon::parse($equipo->ultimo_mantenimiento)->format('d/m/Y') }}
                                            </div>
                                        @else
                                            <div class="small text-muted opacity-50">Sin registro</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if (in_array(mb_strtolower($equipo->estado ?? ''), ['mantenimiento', 'en mantenimiento']))
                                            <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold shadow-sm"><i
                                                    class="bi bi-tools"></i> Mantenimiento</span>
                                        @elseif (in_array(mb_strtolower($equipo->estado ?? ''), ['disponible', 'activo', 'optimo', 'óptimo']))
                                            <span
                                                class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2 fw-bold">Disponible</span>
                                        @else
                                            <span
                                                class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-3 py-2 fw-bold text-capitalize">{{ $equipo->estado }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-pc-display fs-1 opacity-25 d-block mb-3"></i>
                                        No hay equipos registrados en el sistema.
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
            @foreach ($equiposLaboratorios as $nombreLab => $equiposLab)
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        {{-- Encabezado verde institucional --}}
                        <div class="card-header text-white border-0 py-3 px-4" style="background-color: #009B4D;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-door-open me-2"></i> {{ $nombreLab }}</h6>
                                <span class="badge bg-white text-dark rounded-pill shadow-sm">
                                    {{ $equiposLab->count() }} PCs
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-0 bg-white">
                            {{-- SCROLL INTERNO PARA MANTENER LA SIMETRÍA (400px) --}}
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.70rem;">
                                            <th class="ps-4 py-2 border-0">Equipo</th>
                                            <th class="text-center py-2 border-0"
                                                title="Usos desde el último mantenimiento">Rec.</th>
                                            <th class="text-center py-2 border-0" title="Usos en toda su historia">Total
                                            </th>
                                            <th class="text-center py-2 pe-4 border-0">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($equiposLab as $equipo)
                                            <tr class="border-bottom">
                                                <td class="ps-4 py-3 fw-bold text-dark small">
                                                    PC #{{ $equipo->numero_maquina }}
                                                </td>
                                                <td class="text-center py-3">
                                                    <span
                                                        class="badge bg-light text-dark border border-secondary border-opacity-25 rounded-pill px-2"
                                                        title="Usos Recientes">
                                                        {{ $equipo->usos_acumulados }}
                                                    </span>
                                                </td>
                                                <td class="text-center py-3">
                                                    <span class="small fw-bold text-muted" title="Histórico Total">
                                                        <i class="bi bi-speedometer2 opacity-50"></i>
                                                        {{ $equipo->usos_historicos }}
                                                    </span>
                                                </td>
                                                <td class="text-center py-3 pe-4">
                                                    @if (in_array(mb_strtolower($equipo->estado ?? ''), ['mantenimiento', 'en mantenimiento']))
                                                        <i class="bi bi-tools text-danger" title="En Mantenimiento"></i>
                                                    @elseif (in_array(mb_strtolower($equipo->estado ?? ''), ['disponible', 'activo', 'optimo', 'óptimo']))
                                                        <i class="bi bi-check-circle-fill text-success"
                                                            title="Disponible"></i>
                                                    @else
                                                        <i class="bi bi-exclamation-circle-fill text-secondary"
                                                            title="{{ $equipo->estado }}"></i>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted small">
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
