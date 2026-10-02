@extends('layouts.admin')
@section('title', 'Crear Nuevo Grupo (Lista de clase)')

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-collection-fill text-marca-green me-2"></i> Registrar Nuevo Grupo
            </h3>
            <p class="text-muted small mb-0 mt-1">Crea una lista de clase suelta. Para dar de alta todas las del
                semestre de una vez, usa <strong>Importar desde Excel</strong> en el listado.</p>
        </div>
        <a href="{{ route('grupos.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario Estilo Tarjeta --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('grupos.store') }}" method="POST">
                @csrf {{-- Token de seguridad OBLIGATORIO en Laravel --}}

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DEL GRUPO --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Información de la Materia
                        </h6>
                    </div>

                    {{-- Nombre del Grupo --}}
                    <div class="col-md-6">
                        <label for="nombre_grupo" class="form-label fw-bold">Nombre del Grupo / Materia <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-fonts text-muted"></i></span>
                            <input type="text" class="form-control bg-light @error('nombre_grupo') is-invalid @enderror"
                                id="nombre_grupo" name="nombre_grupo" placeholder="Ej: 1SM-Fundamentos de Programación" required
                                value="{{ old('nombre_grupo') }}">
                        </div>
                        @error('nombre_grupo')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Semestre: no se elige, el sistema siempre asigna el activo.
                         Antes había aquí un selector, pero el guardado lo ignoraba y usaba
                         el activo de todas formas, así que prometía algo que no cumplía.
                         Si se registra en el semestre equivocado, se corrige en Editar. --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Semestre al que pertenece</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                            <div class="form-control bg-light d-flex align-items-center text-truncate"
                                title="{{ $semestreActivo->nombre }}">
                                <span class="fw-semibold text-marca-black">{{ $semestreActivo->nombre }}</span>
                            </div>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-marca-green"></i> Los grupos se registran en el semestre activo.
                        </small>
                    </div>

                </div>

                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
