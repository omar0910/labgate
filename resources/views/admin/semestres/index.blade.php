@extends('layouts.admin')
@section('title', 'Gestión de Semestres')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }

        /* Resaltado suave para el semestre activo */
        .fila-activa {
            background-color: rgba(0, 155, 77, 0.05) !important;
            border-left: 4px solid #009B4D !important;
        }
    </style>

    {{-- Encabezado Moderno --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-calendar3 text-marca-green me-2"></i> Gestión de Periodo
                </h3>
            <p class="text-muted small mb-0 mt-1">Administración de semestres y configuración del periodo activo.</p>
        </div>

        {{-- BOTÓN DE VOLVER ARRIBA --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- Tarjeta de Filtros y Acciones (Buscador y Botón Nuevo) --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form action="{{ route('semestres.index') }}" method="GET" class="mb-0">
                <div class="row g-3 align-items-end justify-content-between">

                    {{-- Buscador --}}
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Semestre</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                style="border: 1px solid #ced4da; flex-grow: 1;">
                                <span class="input-group-text bg-white border-0 text-marca-green">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                    placeholder="Ej. Agosto, 2024, Enero..." value="{{ request('search') }}"
                                    autocomplete="off">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                    Buscar
                                </button>
                            </div>

                            @if (request('search'))
                                <a href="{{ route('semestres.index') }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                    title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botón Nuevo Semestre Abajo --}}
                    <div class="col-md-6 col-lg-5 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end">
                            <a href="{{ route('semestres.create') }}"
                                class="btn btn-marca-black rounded-pill shadow-sm px-3 fw-bold">
                                <i class="bi bi-plus-lg me-1"></i> Añadir Nuevo Semestre
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- Mensajes de Éxito / Error --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabla de Semestres --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Nombre del Semestre</th>
                        <th class="border-0">Duración (Fechas)</th>
                        <th class="text-center border-0">Estado</th>
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($semestres as $semestre)
                        {{-- Aplicamos la clase fila-activa si es el semestre actual --}}
                        <tr class="shadow-hover border-bottom border-light {{ $semestre->es_activo ? 'fila-activa' : '' }}">

                            {{-- 1. NOMBRE --}}
                            <td class="ps-4 py-3">
                                <span class="fw-bold text-marca-black fs-6">
                                    {{ $semestre->nombre }}
                                </span>
                            </td>

                            {{-- 2. FECHAS --}}
                            <td>
                                @if ($semestre->fecha_inicio && $semestre->fecha_fin)
                                    <div class="d-flex align-items-center text-secondary small">
                                        <i class="bi bi-calendar-event me-2 text-muted"></i>
                                        <span>{{ \Carbon\Carbon::parse($semestre->fecha_inicio)->format('d/m/Y') }}</span>
                                        <i class="bi bi-arrow-right mx-2 text-muted" style="font-size: 0.7rem;"></i>
                                        <span>{{ \Carbon\Carbon::parse($semestre->fecha_fin)->format('d/m/Y') }}</span>
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic"><i class="bi bi-calendar-x me-1"></i> Sin
                                        fechas definidas</span>
                                @endif
                            </td>

                            {{-- 3. ESTADO --}}
                            <td class="text-center">
                                @if ($semestre->es_activo)
                                    <span class="badge bg-marca-green text-white rounded-pill px-3 py-2 shadow-sm">
                                        <i class="bi bi-check-circle-fill me-1"></i> ACTIVO ACTUAL
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-3 py-2">
                                        Inactivo
                                    </span>
                                @endif
                            </td>

                            {{-- 4. ACCIONES --}}
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="{{ route('semestres.edit', $semestre->id) }}"
                                        class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                        title="Editar Semestre">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <form action="{{ route('semestres.destroy', $semestre->id) }}" method="POST"
                                        class="d-inline form-eliminar" data-nombre="{{ $semestre->nombre }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                            title="Eliminar Semestre">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted border-0">
                                <div class="d-flex flex-column align-items-center">
                                    @if (request('search'))
                                        <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">No se encontraron resultados</span>
                                        <small>Ningún semestre coincide con la búsqueda.</small>
                                    @else
                                        <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">Directorio vacío</span>
                                        <small>No hay semestres registrados en el sistema actualmente.</small>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación agregada --}}
        @if ($semestres->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $semestres->links() }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const nombreSemestre = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar el semestre "${nombreSemestre}". Esta acción podría afectar grupos, horarios y asistencias vinculadas a este periodo.`,
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
