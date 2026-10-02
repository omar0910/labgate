@extends('layouts.admin')
@section('title', 'Añadir Nueva Materia')

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-book-half text-marca-green me-2"></i> Añadir Nueva Materia</h3>
            <p class="text-muted small mb-0 mt-1">Registra una nueva asignatura en el catálogo del sistema.</p>
        </div>
        <a href="{{ route('materias.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario Estilo Tarjeta --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('materias.store') }}" method="POST">
                @csrf {{-- Token de seguridad OBLIGATORIO en Laravel --}}

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DE LA ASIGNATURA --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Datos de la Asignatura
                        </h6>
                    </div>

                    {{-- CAMPO NOMBRE DE LA MATERIA (Ocupa todo el ancho) --}}
                    <div class="col-md-12">
                        <label for="nombre_materia" class="form-label fw-bold">Nombre de la Materia <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-fonts text-muted"></i></span>
                            <input type="text"
                                class="form-control bg-light text-uppercase @error('nombre_materia') is-invalid @enderror"
                                id="nombre_materia" name="nombre_materia" placeholder="Ej: CÁLCULO DIFERENCIAL" required
                                value="{{ old('nombre_materia') }}">
                        </div>
                        @error('nombre_materia')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- CAMPO CLAVE (Mitad de ancho) --}}
                    <div class="col-md-6">
                        <label for="clave" class="form-label fw-bold">Clave de la Materia</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-muted"></i></span>
                            <input type="text"
                                class="form-control bg-light text-uppercase @error('clave') is-invalid @enderror"
                                id="clave" name="clave" placeholder="Ej: ARM1030" value="{{ old('clave') }}">
                        </div>
                        @error('clave')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- CAMPO CRÉDITOS (Mitad de ancho) --}}
                    <div class="col-md-6">
                        <label for="creditos" class="form-label fw-bold">Créditos (Formato SATCA)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-123 text-muted"></i></span>
                            <input type="text" class="form-control bg-light @error('creditos') is-invalid @enderror"
                                id="creditos" name="creditos" placeholder="Ej: 2-2-4" value="{{ old('creditos') }}">
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-marca-green"></i> Teórico - Práctico - Total
                        </small>
                        @error('creditos')
                            <br><span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                {{-- BOTÓN DE GUARDAR --}}
                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Materia
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
