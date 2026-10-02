@extends('layouts.admin')
@section('title', 'Añadir Nuevo Centro de Cómputo')

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-pc-display text-marca-green me-2"></i> Añadir Centro de Cómputo
            </h3>
            <p class="text-muted small mb-0 mt-1">Registra un nuevo laboratorio o espacio de trabajo en el sistema.</p>
        </div>
        <a href="{{ route('centros-computo.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario Estilo Tarjeta --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('centros-computo.store') }}" method="POST">
                @csrf {{-- Token de seguridad OBLIGATORIO en Laravel --}}

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DEL LABORATORIO --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Información del Laboratorio
                        </h6>
                    </div>

                    {{-- CAMPO NOMBRE DEL CENTRO (Ocupa todo el ancho) --}}
                    <div class="col-md-12">
                        <label for="nombre_centro" class="form-label fw-bold">Nombre del Centro / Laboratorio <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-building text-muted"></i></span>
                            <input type="text"
                                class="form-control bg-light text-uppercase @error('nombre_centro') is-invalid @enderror"
                                id="nombre_centro" name="nombre_centro" placeholder="Ej: CAD 1"
                                required value="{{ old('nombre_centro') }}">
                        </div>
                        @error('nombre_centro')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- CAMPO CAPACIDAD (Mitad de ancho) --}}
                    <div class="col-md-6">
                        <label for="capacidad" class="form-label fw-bold">Capacidad de Máquinas <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person-workspace text-muted"></i></span>
                            {{-- Se deja 30 como valor por defecto, a menos que el usuario haya puesto otro valor antes de un error de validación --}}
                            <input type="number" class="form-control bg-light @error('capacidad') is-invalid @enderror"
                                id="capacidad" name="capacidad" min="1" required value="{{ old('capacidad', 30) }}">
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-marca-green"></i> Número total de computadoras operativas.
                        </small>
                        @error('capacidad')
                            <br><span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- CAMPO USO LIBRE (Mitad de ancho) --}}
                    <div class="col-md-6">
                        <label for="permite_uso_libre" class="form-label fw-bold">¿Permite Uso Libre? <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-unlock text-muted"></i></span>
                            <select class="form-select bg-light @error('permite_uso_libre') is-invalid @enderror"
                                id="permite_uso_libre" name="permite_uso_libre" required>
                                <option value="1" {{ old('permite_uso_libre', '1') == '1' ? 'selected' : '' }}>Sí,
                                    permitir (Clases y Uso Libre)</option>
                                <option value="0" {{ old('permite_uso_libre') === '0' ? 'selected' : '' }}>No,
                                    exclusivo para clases</option>
                            </select>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-marca-green"></i> Define si los alumnos pueden entrar cuando no
                            hay clase.
                        </small>
                        @error('permite_uso_libre')
                            <br><span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- DESDE DÓNDE SE REGISTRA EL USO LIBRE --}}
                    <div class="col-md-6">
                        <label for="uso_libre_solo_en_sus_pcs" class="form-label fw-bold">¿Desde dónde se registra el Uso
                            Libre?</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-pc-display-horizontal text-muted"></i></span>
                            <select class="form-select bg-light" id="uso_libre_solo_en_sus_pcs" name="uso_libre_solo_en_sus_pcs">
                                <option value="0" {{ ! old('uso_libre_solo_en_sus_pcs', false) ? 'selected' : '' }}>
                                    Desde cualquier equipo (se escribe el número de PC)
                                </option>
                                <option value="1" {{ old('uso_libre_solo_en_sus_pcs', false) ? 'selected' : '' }}>
                                    Solo desde las computadoras del laboratorio
                                </option>
                            </select>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-marca-green"></i> "Solo desde sus computadoras" impide registrar
                            Uso Libre con una laptop. Actívalo cuando el script de bloqueo ya esté instalado en todas las
                            PCs de este laboratorio.
                        </small>
                    </div>

                </div>

                {{-- BOTÓN DE GUARDAR --}}
                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Centro
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
