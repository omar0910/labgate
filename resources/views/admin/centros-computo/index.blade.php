@extends('layouts.admin')
@section('title', 'Gestión de Centros de Cómputo')

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
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-pc-display text-marca-green me-2"></i> Centros de Cómputo
            </h3>
            <p class="text-muted small mb-0 mt-1">Administra los laboratorios, su capacidad y permisos de uso.</p>
        </div>

        {{-- BOTÓN DE VOLVER ARRIBA --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- 2. BARRA DE ACCIÓN --}}
    {{-- Este módulo no lleva buscador (son pocos laboratorios), así que la barra
         solo alinea el botón a la derecha en lugar de dejar un hueco vacío. --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-3 px-4 d-flex justify-content-end align-items-center">
            <a href="{{ route('centros-computo.create') }}" class="btn btn-marca-black rounded-pill shadow-sm px-4 fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Añadir Nuevo Centro
            </a>
        </div>
    </div>

    {{-- 3. MENSAJES DE ALERTA --}}
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

    {{-- 4. TABLA DE CENTROS --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 border-0">Nombre del Centro</th>
                        <th class="border-0">Capacidad</th>
                        <th class="border-0">Uso Libre</th>
                        <th class="text-center pe-4 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($centrosComputo as $centro)
                        <tr class="shadow-hover border-bottom border-light">

                            {{-- NOMBRE --}}
                            <td class="ps-4 py-3">
                                <span class="fw-bold text-marca-black fs-6">{{ $centro->nombre_centro }}</span>
                                {{-- El número que piden los instaladores del bloqueo en cada PC --}}
                                <small class="d-block text-muted">Número de laboratorio: <strong>{{ $centro->id }}</strong></small>
                            </td>

                            {{-- CAPACIDAD --}}
                            <td>
                                <span class="fw-bold text-secondary">
                                    <i class="bi bi-person-workspace me-1"></i> {{ $centro->capacidad }} PCs
                                </span>
                            </td>

                            {{-- USO LIBRE --}}
                            <td>
                                @if ($centro->permite_uso_libre)
                                    <span class="badge bg-marca-green text-white rounded-pill px-3 py-1">
                                        <i class="bi bi-unlock-fill me-1"></i> Permitido
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-3 py-1">
                                        <i class="bi bi-lock-fill me-1"></i> Solo Clases
                                    </span>
                                @endif
                            </td>

                            {{-- ACCIONES --}}
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="{{ route('centros-computo.edit', $centro->id) }}"
                                        class="btn btn-marca-yellow btn-sm rounded-pill shadow-sm fw-bold px-3 text-marca-black"
                                        title="Editar Centro">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    {{-- FORMULARIO DE ELIMINAR CON SWEETALERT --}}
                                    <form action="{{ route('centros-computo.destroy', $centro->id) }}" method="POST"
                                        class="d-inline form-eliminar" data-nombre="{{ $centro->nombre_centro }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                            title="Eliminar Centro">
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
                                    <i class="bi bi-pc-display fs-1 d-block mb-3 opacity-25"></i>
                                    <span class="d-block fw-bold text-dark fs-5">Catálogo vacío</span>
                                    <small>No hay centros de cómputo registrados en el sistema actualmente.</small>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

                    const nombreCentro = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Estás a punto de eliminar el laboratorio "${nombreCentro}". Esta acción no se puede deshacer y afectará a los horarios asignados aquí.`,
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
