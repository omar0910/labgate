@extends('layouts.admin')
@section('title', 'Gestión de Materias')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }

    </style>

    {{-- 1. ENCABEZADO MODERNO --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-book-half text-marca-green me-2"></i> Catálogo de Materias
            </h3>
            <p class="text-muted small mb-0 mt-1">Administra las asignaturas disponibles en el sistema.</p>
        </div>

        {{-- BOTÓN DE VOLVER --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- Tarjeta de Filtros y Acciones Integradas --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form action="{{ route('materias.index') }}" method="GET" class="mb-0">
                <div class="row g-3 align-items-end justify-content-between">

                    {{-- Buscador --}}
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Materia</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                style="border: 1px solid #ced4da; flex-grow: 1;">
                                <span class="input-group-text bg-white border-0 text-marca-green">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                    placeholder="Clave o nombre de materia..." value="{{ request('search') }}"
                                    autocomplete="off">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                    Buscar
                                </button>
                            </div>

                            @if (request('search'))
                                <a href="{{ route('materias.index') }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                    title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botones de Acción (Importar y Nuevo) --}}
                    <div class="col-md-6 col-lg-7 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                            <button type="button" class="btn bg-marca-green text-white rounded-pill shadow-sm px-3 fw-bold"
                                data-bs-toggle="modal" data-bs-target="#importarModal">
                                <i class="bi bi-file-earmark-excel me-1"></i> Importar / Actualizar
                            </button>

                            <a href="{{ route('materias.create') }}"
                                class="btn btn-marca-black rounded-pill shadow-sm px-3 fw-bold">
                                <i class="bi bi-plus-lg me-1"></i>Añadir Nueva Materia
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- 2. MENSAJES DE ALERTA --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 3. TABLA DE MATERIAS --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Clave</th>
                        <th class="border-0">Nombre de la Asignatura</th>
                        <th class="text-center border-0">Créditos</th>
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materias as $materia)
                        <tr class="shadow-hover border-bottom border-light">

                            {{-- CLAVE --}}
                            <td class="ps-4 py-3">
                                @if ($materia->clave)
                                    <span class="fw-bold text-dark font-monospace fs-6">{{ $materia->clave }}</span>
                                @else
                                    <span class="text-muted small fst-italic">S/C</span>
                                @endif
                            </td>

                            {{-- NOMBRE --}}
                            <td>
                                <span class="fw-bold text-marca-black text-uppercase">{{ $materia->nombre_materia }}</span>
                            </td>

                            {{-- CRÉDITOS --}}
                            <td class="text-center">
                                @if ($materia->creditos)
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-1 fs-6">
                                        {{ $materia->creditos }}
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>

                            {{-- ACCIONES --}}
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="{{ route('materias.edit', $materia->id) }}"
                                        class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                        title="Editar Materia">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    {{-- FORMULARIO DE ELIMINAR CON SWEETALERT --}}
                                    <form action="{{ route('materias.destroy', $materia->id) }}" method="POST"
                                        class="d-inline form-eliminar" data-nombre="{{ $materia->nombre_materia }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                            title="Eliminar Materia">
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
                                        <span class="d-block fw-bold text-dark fs-5">No se encontraron materias</span>
                                        <small>Ningún registro coincide con "{{ request('search') }}".</small>
                                        <a href="{{ route('materias.index') }}"
                                            class="btn btn-sm btn-outline-secondary mt-3 rounded-pill">Limpiar búsqueda</a>
                                    @else
                                        <i class="bi bi-book fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">Catálogo vacío</span>
                                        <small>No hay materias registradas en el sistema actualmente.</small>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN --}}
        @if ($materias instanceof \Illuminate\Pagination\LengthAwarePaginator && $materias->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $materias->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL PARA IMPORTAR EXCEL --}}
    <div class="modal fade" id="importarModal" tabindex="-1" aria-labelledby="importarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-marca-black" id="importarModalLabel">
                        <i class="bi bi-file-earmark-excel text-marca-green me-2"></i> Importar Materias
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('materias.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body py-4">

                        <div class="alert bg-light border text-muted small rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-marca-green me-1"></i>
                                Instrucciones de Importación:</h6>
                            <ul class="mb-0 ps-3 mt-2">
                                <li>Si la <strong>CLAVE</strong> ya existe en el sistema, se actualizará el nombre de la
                                    asignatura y sus créditos.</li>
                                <li>Si la <strong>CLAVE</strong> es nueva, se registrará como una materia completamente
                                    nueva en el catálogo.</li>
                                <li>Asegúrate de que tu archivo sea tipo Excel (.xlsx, .xls, .csv).</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <label for="archivo_excel" class="form-label fw-bold">Selecciona tu archivo</label>
                            <input class="form-control bg-light" type="file" id="archivo_excel" name="archivo_excel"
                                accept=".xlsx, .xls, .csv" required>
                        </div>

                        <div class="small text-muted">
                            <p class="mb-2 fw-bold text-dark border-bottom pb-1">El archivo debe tener estos encabezados
                                exactos en la 1ra fila (las demás columnas serán ignoradas):</p>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-secondary px-2 py-1">CLAVE</span>
                                <span class="badge bg-secondary px-2 py-1">NOMBRE DE LA ASIGNATURA</span>
                                <span class="badge bg-secondary px-2 py-1">CRÉDITOS SATCA</span>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill fw-bold px-4"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn bg-marca-green text-white rounded-pill fw-bold px-4">Subir Archivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

{{-- SCRIPT PARA LA ALERTA (SWEETALERT2) --}}
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const nombreMateria = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar la asignatura "${nombreMateria}". Esta acción no se puede deshacer.`,
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
