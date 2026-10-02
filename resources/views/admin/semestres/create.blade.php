@extends('layouts.admin')
@section('title', 'Registrar Nuevo Semestre')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
            border-color: #009B4D;
            box-shadow: 0 0 0 0.25rem rgba(0, 155, 77, 0.25);
        }

        /* Estilizar el switch para que use el verde institucional */
        .form-switch .form-check-input:checked {
            background-color: #009B4D;
            border-color: #009B4D;
        }
    </style>

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-plus text-marca-green me-2"></i> Registrar Semestre</h3>
            <p class="text-muted small mb-0 mt-1">Añade un nuevo periodo semestral al sistema.</p>
        </div>
        <a href="{{ route('semestres.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('semestres.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DEL PERIODO --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Datos del Periodo
                        </h6>
                    </div>

                    {{-- Nombre --}}
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Nombre del Ciclo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-fonts text-muted"></i></span>
                            <input type="text" name="nombre"
                                class="form-control bg-light @error('nombre') is-invalid @enderror"
                                placeholder="Ej: Enero - Junio 2026" required value="{{ old('nombre') }}">
                        </div>
                        @error('nombre')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Fechas --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fecha Inicio <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar-event text-muted"></i></span>
                            <input type="date" name="fecha_inicio"
                                class="form-control bg-light @error('fecha_inicio') is-invalid @enderror" required
                                value="{{ old('fecha_inicio') }}">
                        </div>
                        @error('fecha_inicio')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fecha Fin <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar-check text-muted"></i></span>
                            <input type="date" name="fecha_fin"
                                class="form-control bg-light @error('fecha_fin') is-invalid @enderror" required
                                value="{{ old('fecha_fin') }}">
                        </div>
                        @error('fecha_fin')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Switch de Activo (Estilo Tarjeta) --}}
                    <div class="col-12 mt-4">
                        <div
                            class="p-4 bg-light rounded-4 border d-flex flex-column flex-md-row align-items-md-center justify-content-between shadow-sm">
                            <div class="form-check form-switch fs-5 mb-2 mb-md-0">
                                <input class="form-check-input" type="checkbox" name="es_activo" id="es_activo"
                                    value="1" {{ old('es_activo') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark ms-2" style="cursor: pointer;"
                                    for="es_activo">
                                    Marcar como Semestre Actual
                                </label>
                            </div>
                            <div class="text-muted small text-md-end">
                                <i class="bi bi-exclamation-circle-fill text-marca-green me-1"></i>
                                Al activar este, el semestre anterior<br class="d-none d-md-block"> se desactivará
                                automáticamente.
                            </div>
                        </div>
                    </div>

                </div>

                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Semestre
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
