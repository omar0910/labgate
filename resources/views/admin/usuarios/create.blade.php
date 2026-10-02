@extends('layouts.admin')
@section('title', 'Crear Nuevo Usuario')

@section('content')

    {{-- Encabezado Estilo Profesores --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-person-plus-fill text-marca-green me-2"></i> Registrar Usuario
            </h3>
            <p class="text-muted small mb-0 mt-1">Crea cuentas para Administradores o Encargados del sistema.</p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario Estilo Tarjeta --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('usuarios.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS PERSONALES --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-person-vcard me-2"></i>Datos Personales
                        </h6>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nombre(s) <span class="text-danger">*</span></label>
                        <input type="text" name="nombres"
                            class="form-control bg-light @error('nombres') is-invalid @enderror"
                            value="{{ old('nombres') }}" placeholder="Ej. Juan Carlos" required>
                        @error('nombres')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Apellido Paterno</label>
                        <input type="text" name="apellido_paterno"
                            class="form-control bg-light @error('apellido_paterno') is-invalid @enderror"
                            value="{{ old('apellido_paterno') }}" placeholder="Opcional">
                        @error('apellido_paterno')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Apellido Materno</label>
                        <input type="text" name="apellido_materno"
                            class="form-control bg-light @error('apellido_materno') is-invalid @enderror"
                            value="{{ old('apellido_materno') }}" placeholder="Opcional">
                        @error('apellido_materno')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- SECCIÓN: CUENTA DE USUARIO --}}
                    <div class="col-12 mt-5 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-shield-lock me-2"></i>Credenciales y Rol
                        </h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nombre de Usuario <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person-badge"></i></span>
                            <input type="text" name="username"
                                class="form-control bg-light text-lowercase @error('username') is-invalid @enderror"
                                value="{{ old('username') }}" placeholder="ej. juan.admin" required>
                        </div>
                        @error('username')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Rol del Sistema <span class="text-danger">*</span></label>
                        <select class="form-select bg-light @error('rol') is-invalid @enderror" name="rol" required>
                            <option value="">-- Seleccione un rol --</option>
                            <option value="Administrador" {{ old('rol') == 'Administrador' ? 'selected' : '' }}>
                                Administrador</option>
                            <option value="Encargado" {{ old('rol') == 'Encargado' ? 'selected' : '' }}>Encargado de
                                Laboratorio</option>
                        </select>
                        @error('rol')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Correo Electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email"
                                class="form-control bg-light text-lowercase @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" placeholder="ejemplo@{{ config('marca.dominio_correo') }} (Opcional)">
                        </div>
                        @error('email')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-key"></i></span>
                            <input type="password" name="password"
                                class="form-control bg-light @error('password') is-invalid @enderror"
                                placeholder="Mínimo 8 caracteres" required>
                        </div>
                        @error('password')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
