@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

{{-- La ven el admin y el encargado; el encargado sólo consulta (no elimina registros) --}}
@php
    $esAdmin = Auth::user()->rol === 'Administrador';
    $rutaLista = $esAdmin ? 'admin.reportes.uso-libre' : 'encargado.uso-libre';

    // Se abre desde el Inicio o desde Reportes: "Volver" regresa ahí y el menú marca
    // esa sección. Sin origen, al Inicio.
    $regreso = \App\Support\Origen::de(request()) ?? [
        'url'   => route($esAdmin ? 'admin.dashboard' : 'encargado.inicio'),
        'texto' => 'Volver al Inicio',
        'menu'  => 'inicio',
    ];
    $conOrigen = array_intersect_key(request()->query(), array_flip(['origen', 'semestre_id', 'pestana']));
@endphp

@section('title', 'Historial Uso Libre')

@section('menu', $regreso['menu'])

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .form-control:focus {
            border-color: #009B4D;
            box-shadow: 0 0 0 0.25rem rgba(0, 155, 77, 0.25);
        }

    </style>

    {{-- Encabezado --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-pc-display text-marca-green me-2"></i> Historial Completo:
                Uso Libre</h3>
            <p class="text-muted small mb-0 mt-1">
                {{ $esAdmin ? 'Consulta y gestiona los registros de acceso a los laboratorios.' : 'Consulta los registros de acceso a los laboratorios.' }}
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ $regreso['url'] }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> {{ $regreso['texto'] }}
            </a>
        </div>
    </div>

    {{-- ALERTAS DE ÉXITO --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tarjeta de Filtros --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form action="{{ route($rutaLista) }}" method="GET">
                {{-- Al buscar no se pierde a dónde regresa "Volver" --}}
                @foreach ($conOrigen as $campo => $valor)
                    <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                @endforeach
                <div class="row g-3 align-items-end">

                    {{-- Buscador General con Botón Integrado --}}
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Alumno</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden" style="border: 1px solid #ced4da;">
                            <span class="input-group-text bg-white border-0 text-marca-green">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                placeholder="Nombre, apellidos o matrícula..." value="{{ request('search') }}">
                            <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                Buscar
                            </button>
                        </div>
                    </div>

                    {{-- Filtro de Fecha --}}
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Filtrar por Fecha</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-marca-green"><i
                                    class="bi bi-calendar3"></i></span>
                            <input type="date" name="fecha" class="form-control border-start-0 ps-0 text-muted"
                                value="{{ request('fecha') }}">
                        </div>
                    </div>

                    {{-- Botones de Acción Extras --}}
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-marca-black shadow-sm flex-grow-1 rounded-3 py-2 fw-bold">
                                <i class="bi bi-funnel-fill me-1"></i> Filtrar Fecha
                            </button>

                            {{-- Botón de limpiar filtros (solo aparece si hay algo buscado) --}}
                            @if (request('search') || request('fecha'))
                                <a href="{{ route($rutaLista, $conOrigen) }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 py-2 px-3" title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Resultados --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Fecha</th>
                        <th class="border-0">Alumno</th>
                        <th class="border-0">Laboratorio</th>
                        <th class="border-0">Entrada</th>
                        <th class="border-0">Salida</th>
                        <th class="border-0">Duración</th>
                        @if ($esAdmin)
                            <th class="border-0 pe-4 text-center">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        <tr>
                            <td class="ps-4 border-bottom border-light">
                                <span
                                    class="fw-bold text-dark">{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</span>
                            </td>
                            <td class="border-bottom border-light py-3">
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
                            <td class="border-bottom border-light">
                                @if ($registro->fecha_hora_salida)
                                    <span class="fw-bold text-dark">
                                        {{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->diffInMinutes($registro->fecha_hora_salida) }}
                                        min
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            {{-- BOTÓN DE ELIMINAR ESTILIZADO IGUAL AL DE CENTROS (sólo el admin) --}}
                            @if ($esAdmin)
                            <td class="pe-4 border-bottom border-light text-center">
                                <form action="{{ route('admin.reportes.uso-libre.destroy', $registro->id) }}"
                                    method="POST" class="d-inline form-eliminar"
                                    data-nombre="{{ $registro->user->name }} {{ $registro->user->apellido_paterno }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                        title="Eliminar registro">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                            @endif

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $esAdmin ? 7 : 6 }}" class="text-center py-5 text-muted border-0">
                                <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                <span class="d-block fw-bold text-dark fs-5">No se encontraron registros</span>
                                <small>Intenta con otros términos de búsqueda o cambia la fecha.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($registros->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $registros->links() }}
            </div>
        @endif
    </div>

@endsection

{{-- SCRIPT PARA LA ALERTA (SWEETALERT2) IDÉNTICO AL DE CENTROS (sólo el admin elimina) --}}
@if (Auth::user()->rol === 'Administrador')
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const nombreAlumno = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar el registro de "${nombreAlumno}". Esta acción no se puede deshacer.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        background: '#ffffff',
                        customClass: {
                            popup: 'rounded-4 shadow',
                            confirmButton: 'rounded-pill px-4',
                            cancelButton: 'rounded-pill px-4'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush
@endif
