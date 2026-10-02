@extends('layouts.admin')

@section('title', 'Bitácora Detallada de Uso Libre')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        /* Animación para el estado "En curso" */
        .heartbeat {
            animation: heartbeat 1.5s ease-in-out infinite both;
        }

        @keyframes heartbeat {
            from {
                transform: scale(1);
                transform-origin: center center;
                animation-timing-function: ease-out;
            }

            10% {
                transform: scale(0.91);
                animation-timing-function: ease-in;
            }

            17% {
                transform: scale(0.98);
                animation-timing-function: ease-out;
            }

            33% {
                transform: scale(0.87);
                animation-timing-function: ease-in;
            }

            45% {
                transform: scale(1);
                animation-timing-function: ease-out;
            }
        }
    </style>

    <div class="container-fluid py-4">

        {{-- 1. ENCABEZADO SUPERIOR --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-person-workspace fs-1 text-marca-green me-3"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Bitácora Completa: Uso Libre</h4>
                        <p class="text-muted small mb-0 mt-1">Periodo:
                            <strong>{{ $semestre->nombre ?? 'N/A' }}</strong> | Registros encontrados:
                            <strong>{{ $registros->count() }}</strong>
                        </p>
                    </div>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.index', ['semestre_id' => $semestreSeleccionadoId]) }}#infraestructura"
                        class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- 2. FILTROS DE FECHA (Nueva Tarjeta) --}}
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
            <div class="card-body p-4">
                <form action="{{ route('admin.reportes.uso-libre-detalle') }}" method="GET">
                    {{-- MUY IMPORTANTE: Mandamos el semestre oculto para no perderlo --}}
                    <input type="hidden" name="semestre_id" value="{{ $semestreSeleccionadoId }}">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase"><i
                                    class="bi bi-calendar-event me-1"></i> Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio"
                                value="{{ request('fecha_inicio') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase"><i
                                    class="bi bi-calendar-event me-1"></i> Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" value="{{ request('fecha_fin') }}">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit"
                                    class="btn btn-marca-black fw-bold flex-grow-1 shadow-sm rounded-3 py-2">
                                    <i class="bi bi-funnel-fill me-1"></i> Filtrar Bitácora
                                </button>

                                {{-- Botón para limpiar los filtros si hay alguno activo --}}
                                @if (request('fecha_inicio') || request('fecha_fin'))
                                    <a href="{{ route('admin.reportes.uso-libre-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
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

        {{-- 3. TARJETA DE CONTROLES Y TABLA --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">

            {{-- Encabezado interno con botones de exportación --}}
            <div
                class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-marca-green me-2"></i> Detalle Cronológico
                    </h5>
                    @if (request('fecha_inicio') || request('fecha_fin'))
                        <p class="text-marca-green small mb-0 mt-1 fw-bold">Mostrando resultados filtrados por fecha.</p>
                    @else
                        <p class="text-muted small mb-0 mt-1">Exporta la bitácora completa de este periodo.</p>
                    @endif
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.uso-libre-detalle.pdf', ['semestre_id' => $semestreSeleccionadoId, 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}"
                        class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                    </a>
                    <a href="{{ route('admin.reportes.uso-libre-detalle.excel', ['semestre_id' => $semestreSeleccionadoId, 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}"
                        class="btn bg-marca-green text-white rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </a>
                </div>
            </div>

            {{-- Tabla con Scroll --}}
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase sticky-top">
                            <tr>
                                <th class="ps-4 border-0 py-3">Folio / Fecha</th>
                                <th class="border-0">Alumno</th>
                                <th class="border-0">Laboratorio</th>
                                <th class="border-0">Entrada</th>
                                <th class="border-0">Salida</th>
                                <th class="border-0 pe-4">Duración</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $registro)
                                <tr>
                                    <td class="ps-4 border-bottom border-light py-3">
                                        <div class="small text-muted mb-1">
                                            #{{ str_pad($registro->id, 5, '0', STR_PAD_LEFT) }}</div>
                                        <span
                                            class="fw-bold text-dark">{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="border-bottom border-light">
                                        <div class="fw-bold text-dark text-uppercase" style="font-size: 0.9rem;">
                                            {{ $registro->user->name }} {{ $registro->user->apellido_paterno }}
                                            {{ $registro->user->apellido_materno }}
                                        </div>
                                        <small class="text-muted"><i class="bi bi-person-badge"></i>
                                            {{ $registro->user->matricula }}</small>
                                    </td>
                                    <td class="border-bottom border-light">
                                        <span class="fw-bold text-dark d-block">
                                            {{ $registro->centroComputo->nombre_centro ?? 'N/A' }}
                                        </span>
                                        <small class="text-marca-green">PC #{{ $registro->numero_maquina }}</small>
                                    </td>
                                    <td class="border-bottom border-light">
                                        <span class="text-marca-green fw-bold">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>
                                            {{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('h:i A') }}
                                        </span>
                                    </td>
                                    <td class="border-bottom border-light">
                                        @if ($registro->fecha_hora_salida)
                                            <span class="text-danger fw-bold">
                                                <i class="bi bi-box-arrow-left me-1"></i>
                                                {{ \Carbon\Carbon::parse($registro->fecha_hora_salida)->format('h:i A') }}
                                            </span>
                                        @else
                                            <span class="badge bg-marca-yellow text-dark border shadow-sm heartbeat">
                                                <i class="bi bi-clock-history"></i> En curso...
                                            </span>
                                        @endif
                                    </td>
                                    <td class="border-bottom border-light pe-4">
                                        @if ($registro->fecha_hora_salida)
                                            @php
                                                $entrada = \Carbon\Carbon::parse($registro->fecha_hora_registro);
                                                $salida = \Carbon\Carbon::parse($registro->fecha_hora_salida);
                                                // Calculamos horas y minutos exactos
                                                $minutosTotales = $entrada->diffInMinutes($salida);
                                                $horas = floor($minutosTotales / 60);
                                                $minutos = $minutosTotales % 60;
                                            @endphp
                                            <span class="fw-bold text-dark">
                                                @if ($horas > 0)
                                                    {{ $horas }}h
                                                @endif {{ $minutos }}m
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted border-0">
                                        <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">No se encontraron registros</span>
                                        <small>No hay accesos de uso libre en este periodo o fechas seleccionadas.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
