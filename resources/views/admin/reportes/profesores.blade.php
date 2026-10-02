@extends('layouts.admin')
@section('title', 'Profesores')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }

    </style>

    {{-- Encabezado Moderno --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-person-video3 text-marca-green me-2"></i> Directorio de
                Profesores</h3>
            <p class="text-muted small mb-0 mt-1">
                @if ($verBajas)
                    <span class="badge bg-secondary me-1">Dados de baja</span> Docentes que ya no pueden entrar; sus clases y
                    su historial se conservan.
                @else
                    Lista completa de docentes registrados en el sistema.
                @endif
            </p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- Tarjeta de Filtros y Acciones --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form action="{{ route('admin.reportes.profesores') }}" method="GET" class="mb-0">
                @if ($verBajas)
                    <input type="hidden" name="bajas" value="1">
                @endif
                <div class="row g-3 align-items-end justify-content-between">

                    {{-- Buscador y Botón Limpiar Agrupados --}}
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Profesor</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                style="border: 1px solid #ced4da; flex-grow: 1;">
                                <span class="input-group-text bg-white border-0 text-marca-green">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                    placeholder="Nombre, usuario o RFC..." value="{{ request('search') }}">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                    Buscar
                                </button>
                            </div>

                            @if (request('search'))
                                <a href="{{ route('admin.reportes.profesores', $verBajas ? ['bajas' => 1] : []) }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                    title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botones Importar y Nuevo --}}
                    <div class="col-md-6 col-lg-5 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                            {{-- Alternar entre activos y dados de baja --}}
                            @if ($verBajas)
                                <a href="{{ route('admin.reportes.profesores') }}"
                                    class="btn btn-outline-secondary rounded-pill shadow-sm px-3 fw-bold">
                                    <i class="bi bi-people me-1"></i> Ver activos
                                </a>
                            @elseif ($totalBajas > 0)
                                <a href="{{ route('admin.reportes.profesores', ['bajas' => 1]) }}"
                                    class="btn btn-outline-secondary rounded-pill shadow-sm px-3 fw-bold"
                                    title="Docentes dados de baja: no pueden entrar, pero su historial se conserva">
                                    <i class="bi bi-person-dash me-1"></i> Dados de baja ({{ $totalBajas }})
                                </a>
                            @endif
                            <button type="button" class="btn bg-marca-green text-white rounded-pill shadow-sm px-3 fw-bold"
                                data-bs-toggle="modal" data-bs-target="#modalImportarExcel">
                                <i class="bi bi-file-earmark-excel me-1"></i> Importar / Actualizar
                            </button>
                            <a href="{{ route('admin.profesores.create') }}"
                                class="btn btn-marca-black rounded-pill shadow-sm px-3 fw-bold">
                                <i class="bi bi-plus-lg me-1"></i>Añadir Nuevo Profesor
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

    {{-- Tabla de Profesores --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Nombre / Correo</th>
                        <th class="border-0">Usuario</th>
                        <th class="border-0">RFC</th>
                        <th class="border-0">Academia / Depto.</th>
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profesores as $profe)
                        <tr class="shadow-hover border-bottom border-light">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="fw-bold text-marca-black text-capitalize" style="font-size: 1rem;">
                                            {{ mb_strtolower($profe->name ?? '') }} {{ mb_strtolower($profe->apellido_paterno ?? '') }}
                                            {{ mb_strtolower($profe->apellido_materno ?? '') }}
                                        </div>
                                        <small class="text-muted text-nowrap"><i
                                                class="bi bi-envelope me-1"></i>{{ $profe->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="fw-normal">{{ $profe->username ?? 'Sin usuario' }}</span></td>
                            <td><span
                                    class="fw-normal text-uppercase font-monospace">{{ $profe->rfc ?? 'SIN REGISTRO' }}</span>
                            </td>
                            <td><span class="fw-normal">{{ $profe->academia ?? 'No Asignado' }}</span></td>

                            <td class="text-center pe-4">
                                @if ($verBajas)
                                    {{-- Dado de baja: sólo se puede reactivar --}}
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <form action="{{ route('usuarios.reactivar', $profe->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit"
                                                class="btn bg-marca-green text-white btn-sm rounded-pill shadow-sm fw-bold px-3"
                                                title="Reactivar: vuelve a poder entrar">
                                                <i class="bi bi-person-check me-1"></i> Reactivar
                                            </button>
                                        </form>
                                        @if ($profe->fecha_baja)
                                            <small class="text-muted">Baja: {{ $profe->fecha_baja->format('d/m/Y') }}</small>
                                        @endif
                                    </div>
                                @else
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('admin.profesores.edit', $profe->id) }}"
                                            class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                            title="Editar Información">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        {{-- DAR DE BAJA (con SweetAlert): ya no se borra, para no perder su historial --}}
                                        <form action="{{ route('admin.profesores.destroy', $profe->id) }}" method="POST"
                                            class="d-inline form-eliminar"
                                            data-nombre="{{ $profe->name }} {{ $profe->apellido_paterno }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                                title="Dar de baja">
                                                <i class="bi bi-person-dash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted border-0">
                                <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                <span class="d-block fw-bold text-dark fs-5">No se encontraron profesores</span>
                                <small>Intenta con otra búsqueda o asegúrate de que existan registros.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($profesores->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $profesores->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL PARA IMPORTAR EXCEL (PROFESORES) --}}
    <div class="modal fade" id="modalImportarExcel" tabindex="-1" aria-labelledby="modalImportarExcelLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-marca-black" id="modalImportarExcelLabel">
                        <i class="bi bi-file-earmark-excel text-marca-green me-2"></i> Importar Profesores
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.profesores.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body py-4">
                        <div class="alert bg-light border text-muted small rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-marca-green me-1"></i>
                                Instrucciones de Importación:</h6>
                            <ul class="mb-0 ps-3 mt-2">
                                <li>Si el RFC <strong>ya existe</strong>, se actualizará el nombre y departamento.</li>
                                <li>Si el RFC es <strong>nuevo</strong>, se creará el profesor con contraseña temporal igual
                                    a su RFC.</li>
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
                                exactos en la 1ra fila:</p>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-secondary px-2 py-1">rfc</span>
                                <span class="badge bg-secondary px-2 py-1">nombre</span>
                                <span class="badge bg-secondary px-2 py-1">departamento</span>
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

    {{-- SWEETALERT --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const nombreProfesor = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Dar de baja?',
                        text: `${nombreProfesor} ya no podrá entrar al sistema. Sus clases pasadas y su historial se conservan en los reportes, y puedes reactivarlo cuando quieras.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, dar de baja',
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
@endsection
