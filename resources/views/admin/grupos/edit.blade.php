@extends('layouts.admin')
@section('title', 'Editar Grupo (Lista de clases)')

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-marca-green me-2"></i> Editar Grupo</h3>
            <p class="text-muted small mb-0 mt-1">Modificando la información de <strong>{{ $grupo->nombre_grupo }}</strong>.
            </p>
        </div>
        <a href="{{ route('grupos.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('grupos.update', $grupo->id) }}" method="POST">
                @csrf
                @method('PUT') {{-- Directiva para simular el método PUT --}}

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DEL GRUPO --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Información de la Materia
                        </h6>
                    </div>

                    {{-- Nombre del Grupo --}}
                    <div class="col-md-6">
                        <label for="nombre_grupo" class="form-label fw-bold">Nombre del Grupo (Lista) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-fonts text-muted"></i></span>
                            <input type="text" class="form-control bg-light @error('nombre_grupo') is-invalid @enderror"
                                id="nombre_grupo" name="nombre_grupo" required
                                value="{{ old('nombre_grupo', $grupo->nombre_grupo) }}">
                        </div>
                        @error('nombre_grupo')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Selección de Semestre --}}
                    <div class="col-md-6">
                        <label for="semestre_id" class="form-label fw-bold">Semestre al que pertenece <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                            <select class="form-select bg-light @error('semestre_id') is-invalid @enderror" id="semestre_id"
                                name="semestre_id" required>
                                <option value="">-- Seleccione un semestre --</option>

                                @foreach ($semestres as $semestre)
                                    <option value="{{ $semestre->id }}"
                                        {{ old('semestre_id', $grupo->semestre_id) == $semestre->id ? 'selected' : '' }}>
                                        {{ $semestre->nombre }} {{ $semestre->es_activo ? '(Activo)' : '' }}
                                    </option>
                                @endforeach

                            </select>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-exclamation-triangle text-warning"></i> Cambia el semestre solo si te
                            equivocaste al crearlo.
                        </small>
                        @error('semestre_id')
                            <br><span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn btn-marca-yellow rounded-pill shadow-sm px-5 py-2 fw-bold fs-5 text-marca-black">
                        <i class="bi bi-arrow-repeat me-2"></i> Actualizar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
