@extends('layouts.admin')
@section('title', 'Editar Centro de Cómputo')

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-marca-green me-2"></i> Editar Centro de
                Cómputo</h3>
            <p class="text-muted small mb-0 mt-1">Modificando la información de
                <strong>{{ $centro->nombre_centro }}</strong>.</p>
        </div>
        {{-- OJO: Asumo que la ruta de regreso es centros-computo.index como en las vistas anteriores --}}
        <a href="{{ route('centros-computo.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario Estilo Tarjeta --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            {{-- Mantuve tu ruta original admin.centros-computo.update --}}
            <form action="{{ route('admin.centros-computo.update', $centro->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS DEL LABORATORIO --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-info-square me-2"></i>Información del Espacio
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
                                id="nombre_centro" name="nombre_centro" required
                                value="{{ old('nombre_centro', $centro->nombre_centro) }}">
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
                            <input type="number" class="form-control bg-light @error('capacidad') is-invalid @enderror"
                                id="capacidad" name="capacidad" min="1" required
                                value="{{ old('capacidad', $centro->capacidad) }}">
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
                                {{-- Compara el valor viejo o el de la BD para dejar seleccionada la opción correcta --}}
                                <option value="1"
                                    {{ old('permite_uso_libre', $centro->permite_uso_libre) == 1 ? 'selected' : '' }}>
                                    Sí, permitir (Clases y Uso Libre)
                                </option>
                                <option value="0"
                                    {{ old('permite_uso_libre', $centro->permite_uso_libre) == 0 ? 'selected' : '' }}>
                                    No, exclusivo para clases
                                </option>
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
                                <option value="0" {{ ! old('uso_libre_solo_en_sus_pcs', $centro->uso_libre_solo_en_sus_pcs) ? 'selected' : '' }}>
                                    Desde cualquier equipo (se escribe el número de PC)
                                </option>
                                <option value="1" {{ old('uso_libre_solo_en_sus_pcs', $centro->uso_libre_solo_en_sus_pcs) ? 'selected' : '' }}>
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

                {{-- BOTÓN DE ACTUALIZAR (Amarillo) --}}
                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn btn-marca-yellow rounded-pill shadow-sm px-5 py-2 fw-bold fs-5 text-marca-black">
                        <i class="bi bi-arrow-repeat me-2"></i> Actualizar Centro
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
