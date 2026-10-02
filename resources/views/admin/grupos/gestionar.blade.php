@extends('layouts.admin')
@section('title', 'Gestionar Alumnos')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        /* Checkboxes institucionales */
        .form-check-input:checked {
            background-color: #009B4D;
            border-color: #009B4D;
        }

        /* Hover interactivo para las listas */
        .list-hover:hover {
            background-color: #f8f9fa;
            transition: background-color 0.2s ease;
        }

        /* Scrollbar estilizado para las listas */
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 10px;
        }

        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: #009B4D;
        }
    </style>

    {{-- 1. ENCABEZADO --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-marca-green me-2"></i> Gestión de Alumnos</h3>
            <p class="text-muted small mb-0 mt-1">Modificando integrantes del grupo: <strong
                    class="text-marca-black">{{ $grupo->nombre_grupo }}</strong></p>
        </div>

        {{-- BOTÓN DE VOLVER ARRIBA (Solitario) --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('grupos.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> Volver a Grupos
            </a>
        </div>
    </div>

    {{-- 2. BLOQUE DE ALERTAS --}}
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

    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <div class="d-flex">
                <i class="bi bi-exclamation-circle-fill me-2 fs-4 text-warning"></i>
                <div>
                    {!! session('warning') !!}
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 3. CONTENEDOR PRINCIPAL: DISEÑO A DOS COLUMNAS --}}
    <div class="row g-4">

        {{-- COLUMNA IZQUIERDA: Botón Importar y Lista de Asignados --}}
        <div class="col-lg-5 d-flex flex-column gap-3">

            {{-- Nuevo Bloque: Botón de Importar (Ocupa el ancho completo de la columna) --}}
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0 text-marca-black"><i
                                class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Carga Masiva</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Añadir alumnos desde archivo Excel.</small>
                    </div>
                    <button type="button" class="btn bg-marca-green text-white rounded-pill shadow-sm px-3 fw-bold btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalImportarGrupo">
                        <i class="bi bi-file-earmark-excel me-1"></i> Importar
                    </button>
                </div>
            </div>

            {{-- Tarjeta: Alumnos Asignados --}}
            <div class="card shadow-sm border-0 rounded-4 flex-grow-1">
                <div class="card-header bg-white border-bottom pt-4 pb-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-marca-black mb-0"><i class="bi bi-person-check-fill text-marca-green me-2"></i>
                        Inscritos Actuales</h6>
                    <span
                        class="badge bg-marca-black text-white rounded-pill">{{ $alumnos_en_grupo_coleccion->count() }}</span>
                </div>
                <div class="card-body p-0 d-flex flex-column h-100">
                    @if ($alumnos_en_grupo_coleccion->isEmpty())
                        <div class="p-5 text-center text-muted m-auto">
                            <i class="bi bi-person-x fs-1 opacity-25 mb-2 d-block"></i>
                            <small>Aún no hay alumnos asignados a este grupo.</small>
                        </div>
                    @else
                        <div class="list-group list-group-flush custom-scroll flex-grow-1"
                            style="max-height: 440px; overflow-y: auto;">
                            @foreach ($alumnos_en_grupo_coleccion as $alumno)
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark text-capitalize">
                                            {{ mb_strtolower($alumno->name ?? '') }}
                                            {{ mb_strtolower($alumno->apellido_paterno ?? '') }}
                                            {{ mb_strtolower($alumno->apellido_materno ?? '') }}
                                        </span>
                                        <span class="text-muted small font-monospace"><i
                                                class="bi bi-person-badge me-1"></i>{{ $alumno->matricula }}</span>
                                    </div>
                                    <i class="bi bi-check-circle-fill text-success opacity-50"></i>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA: Lista Maestra y Checkboxes --}}
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 h-100 d-flex flex-column">
                <div class="card-header bg-white border-bottom pt-4 pb-3">
                    <h6 class="fw-bold text-marca-black mb-0"><i class="bi bi-list-task text-marca-green me-2"></i> Directorio
                        de Alumnos (Asignar / Quitar)</h6>
                </div>

                <div class="card-body d-flex flex-column flex-grow-1">

                    {{-- Buscador Interno --}}
                    <form action="{{ route('grupos.gestionar-alumnos', $grupo->id) }}" method="GET" class="mb-3">
                        <div class="input-group shadow-sm rounded-3 overflow-hidden" style="border: 1px solid #ced4da;">
                            <span class="input-group-text bg-white border-0 text-marca-green">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-0 search-input ps-0" name="busqueda"
                                placeholder="Buscar por nombre o matrícula..." value="{{ request('busqueda') }}"
                                autocomplete="off">
                            <button class="btn bg-marca-green text-white fw-bold px-3 border-0" type="submit">Buscar</button>

                            @if (request('busqueda'))
                                <a href="{{ route('grupos.gestionar-alumnos', $grupo->id) }}"
                                    class="btn btn-outline-danger shadow-sm px-3 d-flex align-items-center"
                                    title="Limpiar búsqueda">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </form>

                    {{-- Formulario Principal de Sincronización --}}
                    <form action="{{ route('grupos.sincronizar-alumnos', $grupo->id) }}" method="POST"
                        class="d-flex flex-column flex-grow-1 h-100">
                        @csrf
                        <input type="hidden" name="busqueda" value="{{ request('busqueda') }}">

                        <div class="alert bg-light border text-muted small py-2 mb-3">
                            <i class="bi bi-info-circle-fill text-marca-green me-1"></i>
                            Marca o desmarca las casillas y no olvides guardar los cambios al final.
                        </div>

                        {{-- Lista de Checkboxes con Scroll --}}
                        <div class="border rounded-3 custom-scroll flex-grow-1"
                            style="max-height: 400px; overflow-y: auto; background-color: #fdfdfd;">
                            <div class="list-group list-group-flush">
                                @forelse ($alumnos_todos as $alumno)
                                    <label class="list-group-item list-hover d-flex align-items-center py-3"
                                        style="cursor: pointer;">
                                        <input class="form-check-input fs-5 mt-0 me-3 shadow-sm" type="checkbox"
                                            name="alumnos_ids[]" value="{{ $alumno->id }}"
                                            {{ in_array($alumno->id, $alumnos_en_grupo_ids) ? 'checked' : '' }}>

                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark text-capitalize">
                                                {{ mb_strtolower($alumno->name ?? '') }}
                                                {{ mb_strtolower($alumno->apellido_paterno ?? '') }}
                                                {{ mb_strtolower($alumno->apellido_materno ?? '') }}
                                            </span>
                                            <span class="text-muted small font-monospace"><i
                                                    class="bi bi-person-badge me-1"></i>{{ $alumno->matricula }}</span>
                                        </div>
                                    </label>
                                @empty
                                    <div class="p-5 text-center text-muted">
                                        <i class="bi bi-search fs-1 opacity-25 mb-2 d-block"></i>
                                        <small>No hay alumnos que coincidan con "{{ request('busqueda') }}".</small>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Botón de Guardar Pegado Abajo --}}
                        <div class="text-end mt-4 pt-3 border-top mt-auto">
                            <button type="submit"
                                class="btn btn-marca-yellow rounded-pill shadow-sm px-4 py-2 fw-bold text-marca-black fs-6">
                                <i class="bi bi-save me-2"></i> Guardar Cambios en la Lista
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    {{-- MODAL PARA IMPORTAR EXCEL A GRUPO --}}
    <div class="modal fade" id="modalImportarGrupo" tabindex="-1" aria-labelledby="modalImportarGrupoLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-marca-black" id="modalImportarGrupoLabel">
                        <i class="bi bi-file-earmark-excel text-marca-green me-2"></i> Importar a Grupo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('grupos.importar-excel', $grupo->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body py-4">

                        <div class="alert bg-light border text-muted small rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-marca-green me-1"></i>
                                Instrucciones de Importación:</h6>
                            <ul class="mb-0 ps-3 mt-2">
                                <li>El sistema leerá el archivo y asignará automáticamente los alumnos a este grupo.</li>
                                <li>Asegúrate de que tu archivo sea tipo Excel (.xlsx, .xls, .csv).</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <label for="archivo" class="form-label fw-bold">Selecciona tu archivo</label>
                            <input class="form-control bg-light" type="file" id="archivo" name="archivo"
                                accept=".xlsx, .xls, .csv" required>
                        </div>

                        <div class="small text-muted">
                            <p class="mb-2 fw-bold text-dark border-bottom pb-1">El archivo debe tener estos encabezados
                                exactos en la 1ra fila:</p>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-secondary px-2 py-1">Matricula</span>
                                <span class="badge bg-secondary px-2 py-1">Nombre del estudiante</span>
                            </div>
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
