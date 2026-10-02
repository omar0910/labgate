{{--
    Aviso que sale al entrar al sistema, colgando de la foto de perfil, cuando el
    usuario sigue con la contraseña que se le dio de alta.

    Sólo aparece en la primera pantalla después de iniciar sesión (es un mensaje
    de un uso). A partir de ahí queda el punto rojo en la foto y el recordatorio
    dentro de su menú, hasta que la cambie.
--}}
@if (session('avisar_password'))
    @php
        $datoConocido = Auth::user()->rol === 'Alumno' ? 'tu matrícula' : 'tu RFC';
    @endphp

    <div id="avisoPassword" class="aviso-password shadow" role="alert">
        <div class="aviso-password-flecha"></div>

        <button type="button" class="aviso-password-cerrar" aria-label="Cerrar aviso"
            onclick="document.getElementById('avisoPassword').remove()">
            <i class="bi bi-x-lg"></i>
        </button>

        <div class="d-flex align-items-start">
            <i class="bi bi-shield-exclamation" style="color: #8a6d00; font-size: 1.4rem; line-height: 1;"></i>
            <div class="ms-3 pe-3">
                <div class="fw-bold" style="color: var(--marca-black, #1a1a1a);">Cambia tu contraseña</div>
                <p class="text-muted mb-3 mt-1" style="font-size: .85rem; line-height: 1.45;">
                    Sigues entrando con {{ $datoConocido }}, que es la contraseña que te dio el sistema.
                    Cámbiala para que nadie más pueda entrar con tu cuenta.
                </p>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('profile.show') }}#seguridad" class="btn btn-sm fw-bold text-white"
                        style="background-color: var(--marca-green, #009B4D);">
                        Cambiarla ahora
                    </a>
                    <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none"
                        onclick="document.getElementById('avisoPassword').remove()">
                        Ahora no
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .aviso-password {
            position: fixed;
            top: 76px;
            right: 20px;
            z-index: 1080;
            width: 340px;
            max-width: calc(100vw - 24px);
            background-color: #ffffff;
            border-radius: .75rem;
            border-top: 4px solid var(--marca-yellow, #FFE900);
            padding: 18px 16px 16px;
            animation: avisoPasswordEntra .35s ease-out both;
        }

        .aviso-password-flecha {
            position: absolute;
            top: -11px;
            right: 26px;
            width: 14px;
            height: 14px;
            background-color: var(--marca-yellow, #FFE900);
            transform: rotate(45deg);
            border-radius: 2px;
        }

        .aviso-password-cerrar {
            position: absolute;
            top: 10px;
            right: 10px;
            border: 0;
            background: transparent;
            color: #adb5bd;
            font-size: .8rem;
            line-height: 1;
            padding: 4px;
        }

        .aviso-password-cerrar:hover {
            color: #6c757d;
        }

        @keyframes avisoPasswordEntra {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(.97);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        @media (max-width: 575.98px) {
            .aviso-password {
                right: 12px;
                left: 12px;
                width: auto;
            }

            .aviso-password-flecha {
                right: 20px;
            }
        }
    </style>
@endif
