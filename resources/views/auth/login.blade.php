<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - {{ config('marca.sistema') }}</title>

    {{-- Vite Scripts --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- BOOTSTRAP CDN & Fuentes --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

    <style>
        body,
        html {
            height: 100%;
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            background-color: #ffffff;
        }

        /* COLORES INSTITUCIONALES */
        :root {
            --marca-green: #009B4D;
            --marca-dark-green: #007a3d;
            --marca-yellow: #FFE900;
            --marca-black: #1a1a1a;
            --error-red: #dc3545;
        }

        /* CONTENEDOR PRINCIPAL PANTALLA DIVIDIDA */
        .login-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* LADO IZQUIERDO: IMAGEN */
        .login-image-side {
            display: none;
            position: relative;
            background-image: url('{{ asset('img/portada.png') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        @media (min-width: 992px) {
            .login-image-side {
                display: flex;
                flex: 1.2;
                align-items: center;
                justify-content: center;
            }
        }

        /* CAPA DE COLOR SOBRE LA IMAGEN */
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 155, 77, 0.85) 0%, rgba(26, 26, 26, 0.9) 100%);
            z-index: 1;
        }

        /* TEXTO DE BIENVENIDA SOBRE LA IMAGEN */
        .welcome-text {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
            padding: 3rem;
            max-width: 600px;
        }

        .welcome-text h1 {
            font-weight: 800;
            font-size: 3rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .welcome-text h1 span {
            color: var(--marca-yellow);
        }

        .welcome-text p {
            font-size: 1.2rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-top: 1rem;
        }

        /* LADO DERECHO: FORMULARIO */
        .login-form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: #ffffff;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        .system-badge {
            display: inline-block;
            background-color: var(--marca-yellow);
            color: var(--marca-black);
            padding: 0.4rem 1.2rem;
            border-radius: 50rem;
            font-size: 0.8rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-title {
            color: var(--marca-black);
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
        }

        /* ESTILOS DE LOS INPUTS Y VALIDACIONES */
        .input-group-modern {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group-modern label {
            font-weight: 700;
            color: var(--marca-black);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.icon-left {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 1.2rem;
            transition: color 0.3s ease;
            pointer-events: none;
            /* Evita que el click se quede en el icono */
        }

        .form-control {
            padding: 0.8rem 1rem 0.8rem 45px;
            border-radius: 0.5rem;
            border: 1.5px solid #dee2e6;
            background-color: #f8f9fa;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--marca-green);
            box-shadow: 0 0 0 4px rgba(0, 155, 77, 0.1);
            background-color: #ffffff;
            outline: none;
        }

        .form-control:focus+i.icon-left,
        .input-wrapper:focus-within i.icon-left {
            color: var(--marca-green);
        }

        /* Validación visual HTML5 */
        .form-control:not(:placeholder-shown):invalid {
            border-color: var(--error-red);
            background-color: #fff8f8;
        }

        .form-control:not(:placeholder-shown):invalid:focus {
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.1);
        }

        /* ICONO DE VER CONTRASEÑA */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.2s ease;
            background: none;
            border: none;
            padding: 0;
        }

        .toggle-password:hover {
            color: var(--marca-black);
        }

        /* Modificar padding derecho si hay ojo mágico */
        .input-wrapper.has-eye .form-control {
            padding-right: 45px;
        }

        /* BOTÓN DE ENTRAR */
        .btn-submit {
            background-color: var(--marca-green);
            color: white;
            font-weight: 700;
            border-radius: 0.5rem;
            padding: 0.85rem;
            font-size: 1.1rem;
            width: 100%;
            border: none;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background-color: var(--marca-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 155, 77, 0.25);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: none;
        }
        /* ACCESOS DE LA DEMOSTRACIÓN (sólo con DEMO=true) */
        .demo-accesos {
            border: 2px dashed var(--marca-green);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.75rem;
            background-color: #f4fbf7;
        }

        .demo-titulo {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--marca-black);
            margin-bottom: 0.75rem;
        }

        .demo-botones {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .demo-boton {
            width: 100%;
            border: 1px solid var(--marca-green);
            background-color: #ffffff;
            color: var(--marca-dark-green);
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 0.5rem;
            padding: 0.55rem 0.5rem;
            transition: all 0.15s ease;
        }

        .demo-boton:hover,
        .demo-boton:focus-visible {
            background-color: var(--marca-green);
            color: #ffffff;
        }

        .demo-nota {
            font-size: 0.78rem;
            color: #6c757d;
            margin-top: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">

        {{-- LADO IZQUIERDO: IMAGEN INSTITUCIONAL --}}
        <div class="login-image-side">
            <div class="image-overlay"></div>
            <div class="welcome-text">
                <h1>{{ config('marca.sistema') }}</h1>
                <p>{{ config('marca.lema') }}.</p>
            </div>
        </div>

        {{-- LADO DERECHO: FORMULARIO BLANCO --}}
        <div class="login-form-side">
            <div class="form-container">

                {{-- Encabezado del Formulario --}}
                <div class="system-badge"><i class="bi bi-shield-lock-fill me-1"></i> Acceso Institucional</div>
                <h2 class="form-title">Bienvenido</h2>
                <p class="form-subtitle">Ingresa tus credenciales para acceder al sistema.</p>

                {{-- Demostración: entrar con un clic como cada rol (sólo con DEMO=true) --}}
                @if (config('demo.activo'))
                    <div class="demo-accesos">
                        <div class="demo-titulo"><i class="bi bi-lightning-charge-fill me-1"></i> Demostración: entra con un clic
                        </div>
                        <div class="demo-botones">
                            @foreach (['Administrador' => 'bi-gear-fill', 'Encargado' => 'bi-pc-display', 'Profesor' => 'bi-person-video3', 'Alumno' => 'bi-mortarboard-fill'] as $rol => $icono)
                                <form method="POST" action="{{ route('demo.entrar', $rol) }}">
                                    @csrf
                                    <button type="submit" class="demo-boton"><i class="bi {{ $icono }} me-1"></i>
                                        {{ $rol }}</button>
                                </form>
                            @endforeach
                        </div>
                        <div class="demo-nota">Todos los datos son ficticios y se reinician cada día. También puedes
                            escribir <strong>{{ config('demo.cuentas.Alumno') }}</strong> con la contraseña
                            <strong>{{ config('demo.contrasena') }}</strong>.</div>
                    </div>
                @endif

                {{-- Aviso de la sesión anterior (por ejemplo, la salida de Uso Libre) --}}
                @if (session('success'))
                    <div class="alert d-flex align-items-center py-3 border-0 shadow-sm"
                        style="background-color: #e6f6ed; color: #0a6b3d; border-radius: 0.5rem; margin-bottom: 2rem;">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div style="font-size: 0.95rem; font-weight: 600; line-height: 1.3;">{{ session('success') }}
                        </div>
                    </div>
                @endif

                {{-- Alertas de Error del Servidor --}}
                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-center py-3 border-0 shadow-sm"
                        style="background-color: #fee2e2; color: #991b1b; border-radius: 0.5rem; margin-bottom: 2rem;">
                        <i class="bi bi-exclamation-octagon-fill me-3 fs-4"></i>
                        <div style="font-size: 0.95rem; font-weight: 600; line-height: 1.3;">{{ $errors->first() }}
                        </div>
                    </div>
                @endif

                {{-- Formulario --}}
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf

                    {{-- IDENTIFICADOR --}}
                    <div class="input-group-modern">
                        <label for="login">Identificador</label>
                        <div class="input-wrapper">
                            <input type="text" class="form-control" id="login" name="login"
                                value="{{ old('login') }}" required autofocus placeholder="Usuario, Matrícula o RFC">
                            <i class="bi bi-person-fill icon-left"></i>
                        </div>
                    </div>

                    {{-- CONTRASEÑA (Con Ojo Mágico) --}}
                    <div class="input-group-modern">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper has-eye">
                            <input type="password" class="form-control" id="password" name="password" required
                                placeholder="Ingresa tu contraseña">
                            <i class="bi bi-lock-fill icon-left"></i>

                            {{-- Botón para ver contraseña --}}
                            <button type="button" class="toggle-password" id="togglePasswordBtn" tabindex="-1"
                                title="Mostrar/Ocultar contraseña">
                                <i class="bi bi-eye-slash-fill" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit d-flex align-items-center justify-content-center">
                        Entrar <i class="bi bi-box-arrow-in-right ms-2"></i>
                    </button>
                </form>

            </div>
        </div>

    </div>

    {{-- SCRIPT PARA EL OJO MÁGICO --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('password');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');

            togglePasswordBtn.addEventListener('click', function() {
                // Alternar el tipo de input (password -> text)
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Alternar el icono (ojo abierto -> ojo cerrado)
                if (type === 'text') {
                    togglePasswordIcon.classList.remove('bi-eye-slash-fill');
                    togglePasswordIcon.classList.add('bi-eye-fill');
                    togglePasswordIcon.style.color = 'var(--marca-green)'; // Pintarlo verde al revelar
                } else {
                    togglePasswordIcon.classList.remove('bi-eye-fill');
                    togglePasswordIcon.classList.add('bi-eye-slash-fill');
                    togglePasswordIcon.style.color = ''; // Volver al gris original
                }

                // Mantener el foco en el input para que el usuario pueda seguir escribiendo
                passwordInput.focus();
            });
        });
    </script>
</body>

</html>
