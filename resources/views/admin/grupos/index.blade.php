@extends('layouts.admin')
@section('title', 'Gestión de Grupos (Listas de clases)')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }
    </style>

    {{-- Encabezado Moderno e Indicador de Semestre --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black">
                <i class="bi bi-collection-fill text-marca-green me-2"></i> Listas de Clases
            </h3>

            <div class="mt-2">
                @if (isset($semestreActivo) && $semestreActivo)
                    <span class="badge bg-marca-green bg-opacity-10 text-marca-green border border-marca-green px-3 py-2"
                        style="font-size: 0.85rem;">
                        <i class="bi bi-calendar-check-fill me-1"></i>
                        Semestre Activo: {{ $semestreActivo->nombre }}
                    </span>
                @else
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2"
                        style="font-size: 0.85rem;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        No hay semestre activo. <a href="{{ route('semestres.index') }}"
                            class="text-danger fw-bold text-decoration-underline ms-1">Activar aquí</a>
                    </span>
                @endif
            </div>
        </div>

        {{-- BOTÓN DE VOLVER ARRIBA --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
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

    {{-- BUSCADOR Y ACCIONES --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form action="{{ route('grupos.index') }}" method="GET" class="mb-0">
                <div class="row g-3 align-items-end justify-content-between">

                    {{-- Buscador --}}
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Grupo</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                style="border: 1px solid #ced4da; flex-grow: 1;">
                                <span class="input-group-text bg-white border-0 text-marca-green">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                    placeholder="Buscar grupo o materia..." value="{{ request('search') }}"
                                    autocomplete="off">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                    Buscar
                                </button>
                            </div>

                            @if (request('search'))
                                <a href="{{ route('grupos.index') }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                    title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botones de Acción (Importar y Nuevo) --}}
                    {{-- Ambos necesitan un semestre activo, que es al que se asignan los grupos. --}}
                    <div class="col-md-6 col-lg-7 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                            @if (isset($semestreActivo) && $semestreActivo)
                                <button type="button"
                                    class="btn bg-marca-green text-white rounded-pill shadow-sm px-3 fw-bold"
                                    data-bs-toggle="modal" data-bs-target="#importarModal"
                                    title="Crear todos los grupos desde el archivo de Excel">
                                    <i class="bi bi-file-earmark-excel me-1"></i> Importar desde Excel
                                </button>

                                <a href="{{ route('grupos.create') }}"
                                    class="btn btn-marca-black rounded-pill shadow-sm px-3 fw-bold">
                                    <i class="bi bi-plus-lg me-1"></i>Añadir Nuevo Grupo
                                </a>
                            @else
                                <a href="{{ route('semestres.index') }}"
                                    class="btn btn-outline-secondary rounded-pill shadow-sm px-3 fw-bold">
                                    <i class="bi bi-calendar2-check me-1"></i> Ir a Periodos
                                </a>
                            @endif
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- LISTADO DE GRUPOS --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">

        {{-- Encabezado de la lista con el total --}}
        <div class="d-flex justify-content-between align-items-center px-4 pt-4 pb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-list-ul text-marca-green me-2"></i> Grupos Registrados
            </h5>

            @if (isset($semestreActivo) && $semestreActivo)
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                    <i class="bi bi-collection me-1"></i>
                    {{ method_exists($grupos, 'total') ? $grupos->total() : count($grupos) }}
                    {{ (method_exists($grupos, 'total') ? $grupos->total() : count($grupos)) == 1 ? 'grupo' : 'grupos' }}
                </span>
            @endif
        </div>

        {{-- Tabla de Grupos --}}
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Nombre del Grupo / Materia</th>
                        @if (isset($grupos) && count($grupos) > 0 && isset($grupos->first()->alumnos_count))
                            <th class="text-center border-0">Alumnos Inscritos</th>
                        @endif
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grupos as $grupo)
                        <tr class="shadow-hover border-bottom border-light">
                            <td class="ps-4 py-3">
                                <span class="fw-bold text-marca-black fs-6">{{ $grupo->nombre_grupo }}</span>
                                <span class="d-block text-secondary small">
                                    <i class="bi bi-calendar2-minus me-1"></i>{{ $grupo->semestre->nombre }}
                                </span>
                            </td>

                            @if (isset($grupo->alumnos_count))
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-1">
                                        <i class="bi bi-people me-1"></i> {{ $grupo->alumnos_count }}
                                    </span>
                                </td>
                            @endif

                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    {{-- Botón 1: Alumnos --}}
                                    <a href="{{ route('grupos.gestionar-alumnos', $grupo->id) }}"
                                        class="btn btn-sm btn-outline-marca-green rounded-pill fw-bold px-3"
                                        title="Gestionar Alumnos">
                                        <i class="bi bi-person-lines-fill me-1"></i> Alumnos
                                    </a>

                                    {{-- Botón 2: Editar --}}
                                    <a href="{{ route('grupos.edit', $grupo->id) }}"
                                        class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                        title="Editar Grupo">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    {{-- Botón 3: Eliminar (SweetAlert) --}}
                                    <form action="{{ route('grupos.destroy', $grupo->id) }}" method="POST"
                                        class="d-inline form-eliminar" data-nombre="{{ $grupo->nombre_grupo }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                            title="Eliminar Grupo">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted border-0">
                                @if (isset($semestreActivo) && $semestreActivo)
                                    <div class="d-flex flex-column align-items-center">
                                        @if (request('search'))
                                            <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                            <span class="d-block fw-bold text-dark fs-5">No se encontraron
                                                resultados</span>
                                            <small>Ningún grupo coincide con la búsqueda.</small>
                                        @else
                                            <i class="bi bi-collection fs-1 d-block mb-3 opacity-25"></i>
                                            <span class="d-block fw-bold text-dark fs-5">Sin grupos
                                                registrados</span>
                                            <small>Importa el archivo de horarios o añade el primero a mano.</small>
                                        @endif
                                    </div>
                                @else
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-exclamation-octagon fs-1 d-block mb-3 opacity-25 text-danger"></i>
                                        <span class="d-block fw-bold text-dark fs-5">Acción Requerida</span>
                                        <small>Para ver o crear grupos, primero debes activar un semestre.</small>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN --}}
        @if (method_exists($grupos, 'hasPages') && $grupos->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $grupos->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL PARA IMPORTAR EXCEL --}}
    <div class="modal fade" id="importarModal" tabindex="-1" aria-labelledby="importarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-marca-black" id="importarModalLabel">
                        <i class="bi bi-file-earmark-excel text-marca-green me-2"></i> Importar Grupos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('grupos.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body py-4">

                        <div class="alert bg-light border text-muted small rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-marca-green me-1"></i>
                                Instrucciones de Importación:</h6>
                            <ul class="mb-0 ps-3 mt-2">
                                <li>Es el <strong>mismo archivo de horarios</strong> que ya usas para importar las
                                    materias.</li>
                                <li>Cada grupo se registra con el formato <strong>GRUPO-ASIGNATURA</strong>, por ejemplo
                                    <em>1SM-Cálculo Diferencial</em>.
                                </li>
                                <li>Los grupos que ya existan <strong>no se duplican</strong>: se omiten y conservan a sus
                                    alumnos inscritos.</li>
                                <li>Todos se registran en el semestre activo:
                                    <strong>{{ $semestreActivo->nombre ?? '' }}</strong>.
                                </li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <label for="archivo_excel" class="form-label fw-bold">Selecciona tu archivo</label>
                            <input class="form-control bg-light" type="file" id="archivo_excel" name="archivo_excel"
                                accept=".xlsx, .xls, .csv" required>
                        </div>

                        <div class="small text-muted">
                            <p class="mb-2 fw-bold text-dark border-bottom pb-1">El archivo sólo necesita dos columnas
                                (las demás se ignoran):</p>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="badge bg-secondary px-2 py-1">GRUPO</span>
                                <span class="badge bg-secondary px-2 py-1">MATERIA</span>
                                <span class="badge bg-light text-dark border px-2 py-1">o ASIGNATURA</span>
                                <span class="badge bg-light text-dark border px-2 py-1">o NOMBRE DE LA ASIGNATURA</span>
                            </div>
                            <p class="mb-0">No importa en qué hoja del libro estén, ni si arriba hay filas de título:
                                el sistema las busca. Si una misma clase aparece repetida —como en los horarios, con
                                una fila por hora—, el grupo se registra una sola vez.</p>
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill fw-bold px-4"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn bg-marca-green text-white rounded-pill fw-bold px-4">Subir
                            Archivo</button>
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

                    const nombreGrupo = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar el grupo "${nombreGrupo}". Esto eliminará a todos los alumnos inscritos de esta lista.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
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
