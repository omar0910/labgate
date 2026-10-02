@extends('layouts.encargado') {{-- Asegúrate de que este sea tu layout correcto --}}

@section('title', 'Reporte de Fallas')

@section('content')

    {{-- ESTILOS PARA ESTA VISTA --}}
    <style>
        .ticket-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ticket-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08) !important;
        }

        .border-dashed {
            border-bottom: 1px dashed #dee2e6;
        }

        .bg-soft-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .bg-soft-success {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-2"
            style="border-color: var(--marca-green) !important;">
            <div>
                <h3 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-tools text-marca-green me-2"></i> Mi Mesa de Ayuda
                </h3>
                <p class="text-muted small mb-0 mt-1">Gestión de reportes de fallas de los laboratorios.</p>
            </div>
            <div class="mt-3 mt-md-0">
                {{-- En verde cuando no hay nada pendiente: el rojo se reserva para lo que sí requiere atención --}}
                <span
                    class="badge {{ $pendientes->count() > 0 ? 'bg-danger bg-opacity-10 text-danger border-danger' : 'bg-success bg-opacity-10 text-marca-green border-marca-green' }} border border-opacity-25 rounded-pill px-3 py-2">
                    <i class="bi {{ $pendientes->count() > 0 ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' }} me-1"></i>
                    {{ $pendientes->count() }} {{ $pendientes->count() == 1 ? 'Pendiente' : 'Pendientes' }}
                </span>
            </div>
        </div>

        {{-- ALERTAS --}}
        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center alert-dismissible fade show mb-4"
                role="alert">
                <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                <div>
                    <h6 class="fw-bold mb-0 text-success">¡Excelente trabajo!</h6>
                    <span class="small">{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- TICKETS PENDIENTES --}}
        <div class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="bg-soft-danger rounded-circle d-flex align-items-center justify-content-center me-3"
                        style="width: 40px; height: 40px;">
                        <i class="bi bi-wrench-adjustable fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Equipos que requieren atención</h5>
                </div>

                {{-- Por laboratorio (sólo los que tienen fallas pendientes) --}}
                @if ($pendientesPorCentro->count() > 1 || $centroId)
                    <div class="d-flex flex-wrap gap-1 ms-md-auto">
                        <a href="{{ route('encargado.incidencias.index') }}"
                            class="btn btn-sm rounded-pill px-3 {{ $centroId ? 'btn-light border' : 'btn-marca-black' }}">
                            Todos ({{ $pendientesPorCentro->sum() }})
                        </a>
                        @foreach ($centros as $centro)
                            @if (($pendientesPorCentro[$centro->id] ?? 0) > 0)
                                <a href="{{ route('encargado.incidencias.index', ['centro_id' => $centro->id]) }}"
                                    class="btn btn-sm rounded-pill px-3 {{ $centroId === $centro->id ? 'btn-marca-black' : 'btn-light border' }}">
                                    {{ $centro->nombre_centro }} ({{ $pendientesPorCentro[$centro->id] }})
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 border-0">Ubicación y Equipo</th>
                                <th class="border-0">Problema Reportado</th>
                                <th class="border-0">Tiempo de Espera</th>
                                <th class="border-0 text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendientes as $ticket)
                                <tr class="border-dashed">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded p-2 me-3 text-center border">
                                                <small class="d-block text-muted" style="font-size: 0.65rem;">PC</small>
                                                <h5 class="fw-bold mb-0">#{{ $ticket->numero_maquina }}</h5>
                                            </div>
                                            <div>
                                                <span
                                                    class="fw-bold text-dark d-block">{{ $ticket->centroComputo->nombre_centro ?? 'Laboratorio' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-danger text-white rounded-pill mb-1">{{ $ticket->categoria }}</span>
                                        <div class="text-muted small text-wrap" style="max-width: 300px;">
                                            {{ $ticket->descripcion }}
                                        </div>
                                        {{-- Quién la reportó (para preguntarle detalles si hace falta) --}}
                                        @if ($ticket->user)
                                            <div class="small text-muted mt-1 text-capitalize">
                                                <i class="bi bi-person me-1"></i>Reportó:
                                                {{ mb_strtolower(trim($ticket->user->name . ' ' . $ticket->user->apellido_paterno), 'UTF-8') }}
                                                <span class="text-lowercase">({{ $ticket->user->rol === 'Alumno' ? 'alumno' : mb_strtolower($ticket->user->rol) }})</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center text-muted small"
                                            title="{{ $ticket->created_at->format('d/m/Y H:i') }}">
                                            <i class="bi bi-clock me-1"></i> {{ $ticket->created_at->diffForHumans() }}
                                        </div>
                                        <div class="small text-muted opacity-75">{{ $ticket->created_at->format('d/m/Y H:i') }}</div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button"
                                            class="btn btn-outline-success rounded-pill btn-sm fw-bold px-3"
                                            data-bs-toggle="modal" data-bs-target="#modalResolver{{ $ticket->id }}">
                                            <i class="bi bi-check-lg"></i> Resolver
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                            style="width: 80px; height: 80px;">
                                            <i class="bi bi-shield-check fs-1 text-success opacity-75"></i>
                                        </div>
                                        <h5 class="fw-bold text-success mb-1">¡Laboratorios al 100%!</h5>
                                        <p class="text-muted mb-0">No hay ningún reporte de falla pendiente.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ÚLTIMAS REPARACIONES --}}
        @if ($resueltas->count() > 0)
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock-history fs-5 text-muted me-2"></i>
                    <h5 class="mb-0 fw-bold text-dark">Últimas Reparaciones</h5>
                </div>
                {{-- Botón directo a la ruta del encargado --}}
                <a href="{{ route('encargado.incidencias.historial') }}"
                    class="btn btn-outline-secondary rounded-pill btn-sm fw-bold px-4 mt-2 mt-sm-0 shadow-sm">
                    Ver historial completo <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-3">
                @foreach ($resueltas as $r)
                    <div class="col-md-6 col-lg-4">
                        <div
                            class="card border border-success border-opacity-25 bg-soft-success shadow-none rounded-3 ticket-card h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="bi bi-check-circle-fill text-success me-1"></i> PC
                                        #{{ $r->numero_maquina }}
                                    </h6>
                                    <small class="text-success fw-bold">{{ $r->fecha_de_cierre->format('d/m/Y') }}</small>
                                </div>
                                <div class="small text-muted mb-1">
                                    <i class="bi bi-geo-alt-fill opacity-50"></i> {{ $r->centroComputo->nombre_centro ?? 'Laboratorio' }}
                                </div>
                                <div class="small text-dark mb-2">
                                    <strong>Falla reportada:</strong> {{ $r->categoria }}
                                </div>

                                @if ($r->nota_resolucion)
                                    <div class="p-2 bg-white rounded-3 border small text-muted mt-2 shadow-sm">
                                        <i class="bi bi-tools text-marca-green me-1"></i> <strong class="text-dark">Acción
                                            tomada:</strong><br>
                                        {{ $r->nota_resolucion }}
                                    </div>
                                @endif
                                {{-- Quién la cerró (sólo se sabe de las que se cerraron desde que se guarda) --}}
                                @if ($r->resolvio)
                                <div class="small text-muted mt-2"><i class="bi bi-person-check text-marca-green me-1"></i>Resolvió:
                                    <span class="text-capitalize">{{ mb_strtolower(trim($r->resolvio->name . ' ' . $r->resolvio->apellido_paterno), 'UTF-8') }}</span></div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div> {{-- FIN DEL CONTAINER --}}

    {{-- MODALES DE RESOLUCIÓN --}}
    @foreach ($pendientes as $ticket)
        <div class="modal fade" id="modalResolver{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered text-start">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0 ps-4 pt-4">
                        <h5 class="modal-title fw-bold text-dark"><i
                                class="bi bi-clipboard-check-fill text-marca-green me-2"></i> Bitácora de Mantenimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    {{-- Formulario apunta directo a la ruta del encargado --}}
                    <form action="{{ route('encargado.incidencias.resolver', $ticket->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="modal-body p-4">
                            <div class="alert bg-soft-success border-0 rounded-3 mb-4">
                                <small class="d-block text-success fw-bold">Problema Original (PC
                                    #{{ $ticket->numero_maquina }}):</small>
                                <span class="text-dark">{{ $ticket->descripcion }}</span>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark small"><i class="bi bi-pencil-square me-1"></i>
                                    Notas de Resolución</label>
                                <textarea name="nota_resolucion" class="form-control bg-light border-0 rounded-3" rows="3"
                                    placeholder="Ej. Se limpió el puerto USB y se reemplazó el teclado dañado. Equipo funcionando al 100%." required></textarea>
                                <div class="form-text small text-muted">Explica brevemente qué se le hizo al equipo. Esto
                                    quedará guardado en el expediente de la PC.</div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 bg-light rounded-bottom-4">
                            <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold shadow-sm"
                                data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">Guardar y
                                Cerrar Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

@endsection
