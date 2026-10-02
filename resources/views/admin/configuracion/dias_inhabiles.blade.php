@extends('layouts.admin')
@section('title', 'Días Inhábiles')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }
    </style>

    <div class="container-fluid py-4">

        {{-- 1. ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-calendar-x text-marca-green me-2"></i> Calendario de
                    Días Inhábiles</h3>
                <p class="text-muted small mb-0 mt-1">Configura las fechas festivas y de asueto para el cálculo exacto de
                    clases.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                    title="Volver al Inicio">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
                </a>
            </div>
        </div>

        {{-- 2. MENSAJES DE ALERTA --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- También los errores del formulario (antes no se veían y parecía que no pasaba nada) --}}
        @include('partials.aviso-error')

        {{-- 3. CONTENIDO PRINCIPAL (Formulario y Tabla) --}}
        <div class="row">

            {{-- Formulario para agregar --}}
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-4 border-bottom pb-2">
                            <i class="bi bi-calendar-plus text-marca-green me-2"></i> Registrar Día Inhábil
                        </h5>

                        <form action="{{ url('admin/dias-inhabiles') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Fecha de Asueto</label>
                                <input type="date" name="fecha" class="form-control bg-light border-0 shadow-sm"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Motivo <small
                                        class="text-lowercase fw-normal">(Ej. Aniversario de la institución)</small></label>
                                <input type="text" name="motivo" class="form-control bg-light border-0 shadow-sm"
                                    placeholder="Escribe el motivo..." required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Semestre Aplicable</label>
                                <select name="semestre_id" class="form-select bg-light border-0 shadow-sm">
                                    @foreach ($semestres as $s)
                                        <option value="{{ $s->id }}" {{ $s->es_activo ? 'selected' : '' }}>
                                            {{ $s->nombre }} {{ $s->es_activo ? '(Activo)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-marca-black rounded-pill shadow-sm w-100 fw-bold py-2">
                                <i class="bi bi-plus-lg me-1"></i> Guardar Día
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Lista de días registrados --}}
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-body p-0">
                        <div class="p-4 border-bottom">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-marca-green me-2"></i> Días
                                Registrados</h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 border-0">Fecha</th>
                                        <th class="border-0">Motivo</th>
                                        <th class="border-0">Semestre</th>
                                        <th class="text-center pe-4 border-0">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($dias as $dia)
                                        <tr class="shadow-hover border-bottom border-light">
                                            <td class="ps-4 py-3">
                                                <span class="fw-bold text-dark fs-6">
                                                    <i
                                                        class="bi bi-calendar-event text-muted me-2"></i>{{ \Carbon\Carbon::parse($dia->fecha)->format('d/m/Y') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-marca-black fw-medium">{{ $dia->motivo }}</span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3 py-1">
                                                    {{ $dia->semestre->nombre }}
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                {{-- Botón Eliminar con SweetAlert --}}
                                                <form action="{{ url('admin/dias-inhabiles/' . $dia->id) }}" method="POST"
                                                    class="d-inline form-eliminar"
                                                    data-nombre="{{ \Carbon\Carbon::parse($dia->fecha)->format('d/m/Y') }} ({{ $dia->motivo }})">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="btn btn-danger btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                                        title="Eliminar Día">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted border-0">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-25"></i>
                                                    <span class="d-block fw-bold text-dark fs-5">Sin Días Inhábiles</span>
                                                    <small>No hay fechas festivas registradas actualmente.</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

{{-- SCRIPT PARA LA ALERTA DE ELIMINAR (SWEETALERT2) --}}
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulariosEliminar = document.querySelectorAll('.form-eliminar');

            formulariosEliminar.forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const nombreDia = this.getAttribute('data-nombre');

                    Swal.fire({
                        title: '¿Eliminar día inhábil?',
                        text: `El día ${nombreDia} volverá a ser tomado en cuenta para el cálculo de clases programadas.`,
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
