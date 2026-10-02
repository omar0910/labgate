@extends('layouts.encargado') {{-- Asegúrate de que este sea tu layout correcto --}}

@section('title', 'Historial Completo de Mantenimiento')

@section('content')
    <div class="container-fluid pb-5">

        {{-- ENCABEZADO: Título a la izquierda, Botón a la derecha --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-2"
            style="border-color: #009B4D !important;">

            {{-- Títulos --}}
            <div>
                <h3 class="mb-0 fw-bold text-dark">Historial de Mantenimiento</h3>
                <p class="text-muted small mb-0 mt-1">Registro histórico de todas las reparaciones realizadas.</p>
            </div>

            {{-- Botón de Volver directo a la ruta del encargado --}}
            <div class="mt-3 mt-md-0">
                <a href="{{ route('encargado.incidencias.index') }}"
                    class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 border-0">Fecha y Equipo</th>
                                <th class="border-0">Problema Reportado</th>
                                <th class="border-0">Acción Tomada (Bitácora)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($historial as $ticket)
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark">{{ $ticket->fecha_de_cierre->format('d/m/Y H:i') }}</div>
                                        <div class="small text-muted">
                                            PC #{{ $ticket->numero_maquina }} -
                                            {{ $ticket->centroComputo->nombre_centro ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-danger text-white rounded-pill mb-1">{{ $ticket->categoria }}</span>
                                        <div class="text-muted small">{{ $ticket->descripcion }}</div>
                                    </td>
                                    <td>
                                        @if ($ticket->nota_resolucion)
                                            <div class="small text-dark"><i
                                                    class="bi bi-check-circle-fill text-success me-1"></i>
                                                {{ $ticket->nota_resolucion }}</div>
                                        @else
                                            <span class="text-muted small fst-italic">Sin notas de resolución.</span>
                                        @endif
                                        {{-- Quién la cerró (sólo se sabe de las que se cerraron desde que se guarda) --}}
                                        @if ($ticket->resolvio)
                                            <div class="small text-muted mt-1"><i class="bi bi-person-check text-marca-green me-1"></i>Resolvió:
                                                <span class="text-capitalize">{{ mb_strtolower(trim($ticket->resolvio->name . ' ' . $ticket->resolvio->apellido_paterno), 'UTF-8') }}</span></div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">No hay registros en el historial.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- Paginación de Laravel --}}
            <div class="card-footer bg-white border-0 py-3">
                {{ $historial->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
