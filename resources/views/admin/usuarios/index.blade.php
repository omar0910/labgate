@extends('layouts.admin')
@section('title', 'Gestión de Usuarios')

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
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-shield-lock-fill text-marca-green me-2"></i> Directorio de
                Usuarios</h3>
            <p class="text-muted small mb-0 mt-1">
                @if ($verBajas)
                    <span class="badge bg-secondary me-1">Dados de baja</span> Cuentas que ya no pueden entrar; su historial se
                    conserva.
                @else
                    Administración exclusiva de usuarios Administradores y Encargados.
                @endif
            </p>
        </div>

        {{-- BOTÓN DE VOLVER --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- Tarjeta de Filtros y Acciones (Igual que Profesores) --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            {{-- Asegúrate de que la ruta apunte a tu método index de usuarios --}}
            <form action="{{ route('usuarios.index') }}" method="GET" class="mb-0">
                @if ($verBajas)
                    <input type="hidden" name="bajas" value="1">
                @endif
                <div class="row g-3 align-items-end justify-content-between">

                    {{-- Buscador y Botón Limpiar Agrupados --}}
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold text-muted small text-uppercase">Buscar Usuario</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                style="border: 1px solid #ced4da; flex-grow: 1;">
                                <span class="input-group-text bg-white border-0 text-marca-green">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                    placeholder="Nombre, usuario o correo..." value="{{ request('search') }}">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-3 border-0">
                                    Buscar
                                </button>
                            </div>

                            @if (request('search'))
                                <a href="{{ route('usuarios.index', $verBajas ? ['bajas' => 1] : []) }}"
                                    class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                    title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botón Nuevo Usuario --}}
                    <div class="col-md-6 col-lg-5 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                            {{-- Alternar entre activos y dados de baja --}}
                            @if ($verBajas)
                                <a href="{{ route('usuarios.index') }}"
                                    class="btn btn-outline-secondary rounded-pill shadow-sm px-3 fw-bold">
                                    <i class="bi bi-people me-1"></i> Ver activos
                                </a>
                            @elseif ($totalBajas > 0)
                                <a href="{{ route('usuarios.index', ['bajas' => 1]) }}"
                                    class="btn btn-outline-secondary rounded-pill shadow-sm px-3 fw-bold"
                                    title="Cuentas dadas de baja: no pueden entrar, pero su historial se conserva">
                                    <i class="bi bi-person-dash me-1"></i> Dados de baja ({{ $totalBajas }})
                                </a>
                            @endif
                            <a href="{{ route('usuarios.create') }}"
                                class="btn btn-marca-black rounded-pill shadow-sm px-3 fw-bold">
                                <i class="bi bi-person-plus-fill me-1"></i> Añadir Nuevo Usuario
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

    {{-- Tabla de Usuarios --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Nombre / Correo</th>
                        <th class="border-0">Usuario de acceso</th>
                        <th class="border-0">Rol del Sistema</th>
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                        {{-- Filtro de seguridad: Si por alguna razón el controlador envía un profesor o alumno, la vista lo ignora --}}
                        @if ($usuario->rol == 'Profesor' || $usuario->rol == 'Alumno')
                            @continue
                        @endif

                        <tr class="shadow-hover border-bottom border-light">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    {{-- Avatar circular con inicial --}}
                                    {{--<div class="bg-light text-marca-green fw-bold rounded-circle d-flex justify-content-center align-items-center me-3 border border-marca-green border-opacity-25 shadow-sm"
                                        style="width: 45px; height: 45px; font-size: 1.2rem;">
                                        {{ Str::upper(Str::substr($usuario->name ?? 'U', 0, 1)) }}
                                    </div>--}}
                                    <div>
                                        <div class="fw-bold text-marca-black text-capitalize" style="font-size: 1rem;">
                                            {{ mb_strtolower($usuario->name ?? '') }}
                                            {{ mb_strtolower($usuario->apellido_paterno ?? '') }}
                                            {{ mb_strtolower($usuario->apellido_materno ?? '') }}
                                        </div>
                                        <small class="text-muted text-nowrap">
                                            <i class="bi bi-envelope me-1"></i>{{ $usuario->email ?? 'Sin registro' }}
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-normal">
                                    {{ $usuario->username }}
                                </span>
                            </td>
                            <td>
                                {{-- Etiquetas de colores según el rol --}}
                                @if ($usuario->rol == 'Administrador')
                                    <span class="fw-normal">
                                        <i class="bi bi-star-fill text-marca-yellow me-1"></i> Administrador
                                    </span>
                                @else
                                    <span class="fw-normal">
                                        <i class="bi bi-person-badge text-white me-1"></i> Encargado
                                    </span>
                                @endif
                            </td>
                            <td class="text-center pe-4">
                                @if ($verBajas)
                                    {{-- Dado de baja: sólo se puede reactivar --}}
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <form action="{{ route('usuarios.reactivar', $usuario->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit"
                                                class="btn bg-marca-green text-white btn-sm rounded-pill shadow-sm fw-bold px-3"
                                                title="Reactivar: vuelve a poder entrar">
                                                <i class="bi bi-person-check me-1"></i> Reactivar
                                            </button>
                                        </form>
                                        @if ($usuario->fecha_baja)
                                            <small class="text-muted">Baja: {{ $usuario->fecha_baja->format('d/m/Y') }}</small>
                                        @endif
                                    </div>
                                @else
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('usuarios.edit', $usuario->id) }}"
                                            class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                            title="Editar Información">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        {{-- DAR DE BAJA (con SweetAlert): ya no se borra, para no perder su historial --}}
                                        <form action="{{ route('usuarios.destroy', $usuario->id) }}" method="POST"
                                            class="d-inline form-eliminar" data-nombre="{{ $usuario->name }}">
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
                            <td colspan="4" class="text-center py-5 text-muted border-0">
                                <i class="bi bi-shield-x fs-1 d-block mb-3 opacity-25"></i>
                                <span class="d-block fw-bold text-dark fs-5">No se encontraron usuarios</span>
                                <small>No hay administradores ni encargados que coincidan con la búsqueda.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($usuarios->hasPages())
            <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>

    {{-- SWEETALERT PARA EL BOTÓN ELIMINAR --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const nombreUsuario = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Dar de baja?',
                        text: `${nombreUsuario} ya no podrá entrar al sistema. Su historial (como los reportes de fallas que levantó) se conserva y puedes reactivarlo cuando quieras.`,
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
