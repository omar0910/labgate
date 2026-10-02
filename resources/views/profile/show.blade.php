@php
    $layout = 'layouts.admin';

    if (Auth::user()->rol == 'Alumno') {
        $layout = 'layouts.alumnos';
    } elseif (Auth::user()->rol == 'Profesor') {
        $layout = 'layouts.profesor';
    } elseif (Auth::user()->rol == 'Encargado') {
        $layout = 'layouts.encargado';
    }
@endphp

@extends($layout)

{{-- Dejamos esto vacío --}}
@section('title', 'Configurar Perfil')

@section('content')
    {{-- Script para el título de la pestaña --}}
    <script>
        document.title = "Mi Perfil";
    </script>

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        /* Oculta el contenedor del título vacío que genera el Layout */
        .container>.border-bottom,
        .container-fluid>.border-bottom {
            display: none !important;
        }

        .btn-marca-green {
            background-color: #009B4D;
            border-color: #009B4D;
            color: white;
        }

        .btn-marca-green:hover {
            background-color: #007a3c;
            border-color: #007a3c;
            color: white;
        }

        .shadow-hover:hover {
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
            transition: all .3s ease;
        }
    </style>

    <div class="container">

        {{-- Título personalizado --}}
        <div class="d-flex justify-content-between align-items-center pb-3 mb-4" style="border-bottom: 2px solid #009B4D;">
            <h2 class="mb-0 fw-bold text-marca-black">Mi Perfil</h2>
            @php
                // A la pantalla de donde se vino. Si se abrió directo (un marcador, una
                // pestaña nueva), al inicio de su rol: history.back() no llevaba a nada.
                $inicioDelRol = route([
                    'Alumno' => 'alumno.dashboard',
                    'Profesor' => 'profesor.dashboard',
                    'Encargado' => 'encargado.inicio',
                ][Auth::user()->rol] ?? 'admin.dashboard');
                $anterior = url()->previous();
                $volverA = $anterior && $anterior !== url()->current() && !str_contains($anterior, '/login')
                    ? $anterior
                    : $inicioDelRol;
            @endphp
            <a href="{{ $volverA }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm"
                style="font-size: 0.9rem;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="row justify-content-center g-4">

            {{-- Tarjeta de Información y Foto --}}
            <div class="col-md-5">
                <div class="card shadow-sm border-0 rounded-4 h-100 animate slideIn shadow-hover"
                    style="animation-delay: 0.1s;">
                    <div class="card-header bg-marca-black text-white fw-bold py-3 border-0"
                        style="border-radius: 1rem 1rem 0 0;">
                        <i class="bi bi-person-circle text-marca-yellow me-2 fs-5 align-middle"></i> Mis Datos
                    </div>
                    <div class="card-body text-center pt-4">

                        {{-- --- ÁREA DE FOTO DE PERFIL --- --}}
                        <div class="mb-4 position-relative d-inline-block profile-photo-container">

                            {{-- 1. EL CÍRCULO (Foto o Iniciales) --}}
                            @if (Auth::user()->profile_photo_path)
                                <img id="profileImagePreview"
                                    src="{{ asset('storage/' . Auth::user()->profile_photo_path) }}" alt="Foto de perfil"
                                    class="rounded-circle border border-4 border-marca-yellow shadow"
                                    style="width: 160px; height: 160px; object-fit: cover;">    
                            @else
                                <div id="profileInitialsPreview"
                                    class="rounded-circle d-flex justify-content-center align-items-center mx-auto border border-4 border-marca-yellow shadow"
                                    style="width: 160px; height: 160px; background-color: #f8f9fa;">
                                    <span class="text-marca-black fw-bold" style="font-size: 5rem;">
                                        {{ Str::upper(Str::substr(Auth::user()->name ?? 'U', 0, 1)) }}
                                    </span>
                                </div>
                                <img id="profileImagePreview" src="#" alt="Previsualización"
                                    class="rounded-circle border border-4 border-marca-yellow shadow d-none"
                                    style="width: 160px; height: 160px; object-fit: cover;">
                            @endif

                            {{-- 2. EL BOTÓN "EDITAR" FLOTANTE --}}
                            <div class="position-absolute bottom-0 end-0 mb-1 me-1">
                                <div class="dropdown">
                                    <button
                                        class="btn btn-marca-yellow rounded-circle shadow border border-2 border-white d-flex align-items-center justify-content-center p-0"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        style="width: 40px; height: 40px;" title="Editar foto">
                                        <i class="bi bi-camera-fill text-marca-black" style="font-size: 1.1rem;"></i>
                                    </button>
                                    <ul class="dropdown-menu shadow border-0 rounded-3">
                                        <li>
                                            <button class="dropdown-item py-2" type="button"
                                                onclick="document.getElementById('photoInputHidden').click()">
                                                <i class="bi bi-upload me-2 text-marca-green"></i> Subir nueva foto
                                            </button>
                                        </li>
                                        @if (Auth::user()->profile_photo_path)
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="{{ route('profile.photo.delete') }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger py-2 fw-bold">
                                                        <i class="bi bi-trash3-fill me-2"></i> Eliminar foto actual
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>

                        </div>
                        {{-- --- FIN ÁREA FOTO --- --}}

                        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data"
                            id="profileForm">
                            @csrf

                            {{-- 1. BOTÓN DE GUARDAR (MOVIDO ARRIBA) --}}
                            <div class="mb-4 d-none" id="savePhotoButtonContainer">
                                <button type="submit"
                                    class="btn btn-marca-green w-100 animate slideIn rounded-pill shadow-sm py-2">
                                    <i class="bi bi-check2-circle me-2"></i> Guardar nueva foto de perfil
                                </button>
                            </div>

                            {{-- 2. INPUT OCULTO --}}
                            <input type="file" name="photo" id="photoInputHidden" class="d-none" accept="image/*">

                            {{-- 3. DATOS DEL USUARIO --}}
                            <div class="text-start px-md-3 mt-3">
                                <div class="mb-4 bg-light p-3 rounded-3 border-start border-4 border-marca-green">
                                    <label class="form-label fw-bold text-marca-green small text-uppercase mb-0"><i
                                            class="bi bi-person-vcard me-1"></i> Nombre Completo</label>
                                    {{-- CORRECCIÓN: Concatenamos Nombre + Apellido Paterno + Apellido Materno --}}
                                    <p class="form-control-plaintext fs-5 fw-bold text-dark mb-0 pb-0">
                                        {{ Auth::user()->name }} {{ Auth::user()->apellido_paterno }}
                                        {{ Auth::user()->apellido_materno }}
                                    </p>
                                </div>

                                <div class="mb-3 ps-2">
                                    <label class="form-label fw-bold text-muted small text-uppercase mb-0">
                                        <i class="bi bi-hash me-1"></i>
                                        {{ Auth::user()->rol == 'Profesor' ? 'No. Empleado / Usuario' : (Auth::user()->rol == 'Encargado' ? 'Usuario' : 'Matrícula / Usuario') }}
                                    </label>
                                    <p class="form-control-plaintext fs-5 text-dark mt-0 pt-0">
                                        {{ Auth::user()->matricula ?? (Auth::user()->username ?? Auth::user()->email) }}
                                    </p>
                                </div>

                                <div class="mb-3 ps-2">
                                    <label class="form-label fw-bold text-muted small text-uppercase mb-0">
                                        <i class="bi bi-envelope-at me-1"></i> Correo Electrónico
                                    </label>
                                    <p class="form-control-plaintext fs-6 text-dark mt-0 pt-0">{{ Auth::user()->email }}</p>
                                </div>

                                <div class="mb-2 ps-2 text-center mt-4 border-top pt-3">
                                    <span class="text-muted small text-uppercase fw-bold d-block mb-2">Nivel de
                                        Acceso</span>
                                    {{-- Cada rol conserva su color, pero dentro de la paleta institucional:
                                         el rojo se reserva para errores y acciones destructivas. --}}
                                    <span
                                        class="badge rounded-pill fs-6 px-4 py-2 shadow-sm
                                        {{ Auth::user()->rol == 'Administrador' ? 'bg-marca-black' : '' }}
                                        {{ Auth::user()->rol == 'Alumno' ? 'bg-light text-marca-black border' : '' }}
                                        {{ Auth::user()->rol == 'Profesor' ? 'bg-marca-green' : '' }}
                                        {{ Auth::user()->rol == 'Encargado' ? 'bg-marca-yellow' : '' }}">
                                        {{ Auth::user()->rol }}
                                    </span>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            {{-- Tarjeta de Cambio de Contraseña --}}
            {{-- El id permite que el aviso de "cambia tu contraseña" traiga al usuario aquí --}}
            <div class="col-md-6" id="seguridad">
                <div class="card shadow-sm border-0 rounded-4 h-100 animate slideIn shadow-hover"
                    style="animation-delay: 0.2s;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                        <h5 class="mb-0 fw-bold text-marca-black"><i class="bi bi-shield-lock-fill text-marca-green me-2"></i>
                            Seguridad de la Cuenta</h5>
                        <p class="text-muted small mt-1">Actualiza tu contraseña para mantener tu cuenta segura.</p>
                    </div>
                    <div class="card-body p-4">

                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show border-0 border-start border-4 border-success shadow-sm rounded-3 py-2"
                                role="alert">
                                <i class="bi bi-check-circle-fill text-success me-2"></i> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <form action="{{ route('profile.update-password') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-4">
                                <label for="current_password"
                                    class="form-label fw-bold text-dark small text-uppercase">Contraseña Actual</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary text-muted"><i
                                            class="bi bi-key"></i></span>
                                    <input type="password" name="current_password"
                                        class="form-control border-secondary @error('current_password') is-invalid @enderror"
                                        required placeholder="Ingresa tu contraseña actual">
                                </div>
                                @error('current_password')
                                    <div class="text-danger small mt-1"><i
                                            class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                                @enderror
                            </div>

                            <hr class="text-muted opacity-25 my-4">

                            <div class="mb-4">
                                <label for="new_password" class="form-label fw-bold text-dark small text-uppercase">Nueva
                                    Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary text-marca-green"><i
                                            class="bi bi-lock"></i></span>
                                    <input type="password" name="new_password"
                                        class="form-control border-secondary @error('new_password') is-invalid @enderror"
                                        required placeholder="Crea una nueva contraseña">
                                </div>
                                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Debe tener al menos 8
                                    caracteres.</div>
                                @error('new_password')
                                    <div class="text-danger small mt-1"><i
                                            class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="new_password_confirmation"
                                    class="form-label fw-bold text-dark small text-uppercase">Confirmar Nueva
                                    Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary text-marca-green"><i
                                            class="bi bi-lock-fill"></i></span>
                                    <input type="password" name="new_password_confirmation"
                                        class="form-control border-secondary" required
                                        placeholder="Repite tu nueva contraseña">
                                </div>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit"
                                    class="btn btn-marca-yellow rounded-pill py-2 shadow-sm text-uppercase letter-spacing-1">
                                    <i class="bi bi-arrow-repeat me-2"></i> Actualizar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Scripts JS --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photoInputHidden');
            const profileImagePreview = document.getElementById('profileImagePreview');
            const profileInitialsPreview = document.getElementById('profileInitialsPreview');
            const saveButtonContainer = document.getElementById('savePhotoButtonContainer');

            photoInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profileImagePreview.src = e.target.result;
                        profileImagePreview.classList.remove('d-none');
                        if (profileInitialsPreview) profileInitialsPreview.classList.add('d-none');
                        saveButtonContainer.classList.remove('d-none');
                        saveButtonContainer.classList.add('d-block');
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
@endsection
