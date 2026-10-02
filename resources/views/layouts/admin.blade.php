<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel de Administración') - Administración</title>

    {{-- 1. FUENTES DE GOOGLE (Poppins para elegancia moderna) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @stack('styles')

    {{-- 2. ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        :root {
            --marca-green: #009B4D;
            /* Verde del logo */
            --marca-yellow: #FFE900;
            /* Amarillo del logo */
            --marca-black: #1a1a1a;
            /* Negro suave */
            --marca-gray: #f4f6f9;
            /* Fondo general */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--marca-gray);
        }

        /* --- NAVBAR PERSONALIZADO --- */
        .navbar-marca {
            background-color: var(--marca-black) !important;
            border-bottom: 3px solid var(--marca-green);
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .navbar-brand {
            color: #fff !important;
            display: flex;
            align-items: center;
            /* Ajuste para que el texto largo no rompa el layout en móviles */
            max-width: 90%;
        }

        /* --- ENLACES DEL MENÚ --- */
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 400;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            margin: 0 5px;
        }

        .nav-link:hover {
            color: #fff !important;
            transform: translateY(-1px);
        }

        /* Estado ACTIVO: Texto blanco + Línea amarilla abajo */
        .nav-link.active {
            color: #fff !important;
            font-weight: 600 !important;
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 3px;
            bottom: -10px;
            left: 0;
            background-color: var(--marca-yellow);
            border-radius: 2px 2px 0 0;
        }

        /* --- DROPDOWN --- */
        .dropdown-menu {
            border: none;
            border-top: 3px solid var(--marca-green);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-radius: 0 0 8px 8px;
        }

        .dropdown-item:hover {
            background-color: rgba(0, 155, 77, 0.1);
            color: var(--marca-green);
            font-weight: 500;
        }

        .dropdown-item.active,
        .dropdown-item:active {
            background-color: var(--marca-green);
            color: #fff;
        }

        /* --- CONTENIDO --- */
        .page-title-border {
            border-left: 5px solid var(--marca-yellow);
            padding-left: 15px;
        }

        /* --- PAGINACIÓN (paleta institucional en lugar del azul de Bootstrap) --- */
        .pagination {
            --bs-pagination-color: var(--marca-black);
            --bs-pagination-hover-color: var(--marca-green);
            --bs-pagination-focus-color: var(--marca-green);
            --bs-pagination-focus-box-shadow: 0 0 0 0.2rem rgba(0, 155, 77, 0.25);
            --bs-pagination-active-bg: var(--marca-green);
            --bs-pagination-active-border-color: var(--marca-green);
        }
    </style>
</head>

<body>

    {{-- NAVBAR --}}
    @include('partials.aviso-demo')

    <nav class="navbar navbar-expand-lg navbar-dark navbar-marca shadow">
        <div class="container-fluid px-4">

            {{-- LOGO + NOMBRE INSTITUCIONAL --}}
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">

                {{-- 1. LA FOTO DEL LOGO (AUMENTADA A 75px) --}}
                <img src="{{ asset(config('marca.logo')) }}" alt="Logo" height="75"
                    class="me-4 d-inline-block align-middle">

                {{-- 2. EL TEXTO (Amarillo) --}}
                <div class="d-flex flex-column justify-content-center" style="line-height: 1.1;">
                    <span class="text-uppercase fw-bold"
                        style="color: var(--marca-yellow); font-size: 0.75rem; letter-spacing: 1px;">
                        {{ config('marca.institucion_linea_1') }}
                    </span>

                    <span class="fw-bold" style="color: var(--marca-yellow); font-size: 0.95rem; letter-spacing: 0.5px;">
                        {{ config('marca.institucion_linea_2') }}
                    </span>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavDropdown">

                {{-- SECCIÓN IZQUIERDA: MENÚ PRINCIPAL --}}
                @php
                    // Una vista puede decir a qué sección pertenece con @section('menu', '...'):
                    // inicio, horarios, monitor, gestion, reportes o fallas. Sirve para las
                    // pantallas que se abren desde otra sección (la Bitácora vive en el
                    // Monitor; Clases de hoy, en el Inicio). Sin ella, manda la ruta.
                    $menu = trim($__env->yieldContent('menu'));
                    $enMenu = fn($seccion, $porRuta) => $menu !== '' ? $menu === $seccion : $porRuta;
                @endphp
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">

                    {{-- 1. INICIO --}}
                    <li class="nav-item">
                        <a class="nav-link {{ $enMenu('inicio', request()->routeIs('admin.dashboard', 'admin.semana')) ? 'active' : '' }}"
                            href="{{ route('admin.dashboard') }}">
                            Inicio
                        </a>
                    </li>

                    {{-- 2. HORARIOS --}}
                    <li class="nav-item">
                        <a class="nav-link {{ $enMenu('horarios', request()->is('admin/horarios*')) ? 'active' : '' }}"
                            href="{{ url('admin/horarios') }}">
                            Horarios
                        </a>
                    </li>

                    {{-- 5. MONITOR (con la Bitácora de Clases, que se abre desde aquí) --}}
                    <li class="nav-item">
                        <a class="nav-link {{ $enMenu('monitor', request()->routeIs('monitor.*', 'admin.bitacora.*')) ? 'active' : '' }}"
                            href="{{ route('monitor.index') }}">
                            Monitor de Laboratorios
                        </a>
                    </li>

                    {{-- 3. GESTIÓN DE DATOS --}}
                    @php
                        $esGestion = request()->is([
                            'admin/usuarios*',
                            'admin/semestres*',
                            'admin/alumnos*',
                            'admin/reportes/profesores*',
                            'admin/profesores*',
                            'admin/grupos*',
                            'admin/materias*',
                            'admin/dias-inhabiles*',
                            'admin/centros-computo*',
                            'admin/mantenimiento/respaldos*', // "Respaldos del Sistema" vive en este menú
                        ]);
                    @endphp

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $enMenu('gestion', $esGestion) ? 'active' : '' }}" href="#"
                            id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Gestión de datos
                        </a>
                        <ul class="dropdown-menu animate slideIn" aria-labelledby="navbarDropdownMenuLink">
                            <li><a class="dropdown-item" href="{{ url('admin/usuarios') }}">Usuarios del Sistema</a>
                            </li>
                            <li><a class="dropdown-item" href="{{ url('admin/alumnos') }}">Alumnos (Matricula)</a></li>

                            {{-- MENÚ DE PROFESORES --}}
                            <li><a class="dropdown-item" href="{{ route('admin.reportes.profesores') }}">Profesores</a>
                            </li>

                            <li><a class="dropdown-item" href="{{ url('admin/semestres') }}">Periodos</a></li>
                            <li><a class="dropdown-item" href="{{ url('admin/materias') }}">Materias</a></li>

                            {{-- NUEVO ORDEN SOLICITADO --}}
                            <li><a class="dropdown-item" href="{{ url('admin/grupos') }}">Enrolamiento de Grupos</a>
                            </li>

                            {{-- Enlace corregido a la ruta correcta --}}
                            <li><a class="dropdown-item" href="{{ url('admin/dias-inhabiles') }}">Calendario Días
                                    Inhábiles</a></li>

                            <li><a class="dropdown-item" href="{{ url('admin/centros-computo') }}">Laboratorios</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('backup.index') }}">
                                    <i class="fas fa-database"></i> Respaldos del Sistema
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- 4. REPORTES --}}
                    {{-- Se descuenta $esGestion: el "Directorio de Profesores" es una ruta
                         admin.reportes.* pero se entra desde Gestión de datos, y sin esta
                         condición quedaban las dos secciones resaltadas a la vez. --}}
                    <li class="nav-item">
                        <a class="nav-link {{ $enMenu('reportes', request()->routeIs('admin.reportes.*') && !$esGestion) ? 'active' : '' }}"
                            href="{{ route('admin.reportes.index') }}">
                            Reportes
                        </a>
                    </li>



                    {{-- 6. FALLAS --}}
                    <li class="nav-item">
                        <a class="nav-link {{ $enMenu('fallas', request()->routeIs('admin.incidencias.*')) ? 'active' : '' }}"
                            href="{{ route('admin.incidencias.index') }}">
                            Reporte Fallas
                        </a>
                    </li>
                </ul>

                {{-- SECCIÓN DERECHA: PERFIL DE USUARIO --}}
                <ul class="navbar-nav ms-auto align-items-center">

                    <li class="nav-item me-3 d-none d-md-block">
                        <div class="text-end" style="line-height: 1.2;">
                            <small class="d-block text-white-50" style="font-size: 0.75rem;">Bienvenido</small>
                            <span class="text-white fw-bold" style="font-size: 0.9rem;">
                                {{ Auth::user()->name }} {{ Auth::user()->apellido_paterno }}
                                {{ Auth::user()->apellido_materno }}
                            </span>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a id="navbarDropdown" class="nav-link p-0 d-flex align-items-center position-relative" href="#"
                            role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                            @if (Auth::user()->profile_photo_path)
                                <img src="{{ asset('storage/' . Auth::user()->profile_photo_path) }}" alt="Avatar"
                                    class="rounded-circle border border-2 shadow-sm"
                                    style="width: 42px; height: 42px; object-fit: cover; border-color: var(--marca-yellow) !important;">
                            @else
                                <div class="rounded-circle d-flex justify-content-center align-items-center shadow-sm border border-2"
                                    style="width: 42px; height: 42px; background-color: #fff; border-color: var(--marca-yellow) !important;">
                                    <span class="fw-bold text-dark" style="font-size: 1.1rem;">
                                        {{ Str::upper(Str::substr(Auth::user()->name ?? 'U', 0, 1)) }}
                                    </span>
                                </div>
                            @endif

                            @include('layouts.partials.aviso-password-punto')
                        </a>

                        <div class="dropdown-menu dropdown-menu-end shadow animate slideIn">
                            <h6 class="dropdown-header text-uppercase small fw-bold" style="color: var(--marca-green);">
                                Mi Cuenta
                            </h6>

                            @include('layouts.partials.aviso-password-menu')
                            <a class="dropdown-item py-2" href="{{ route('profile.show') }}">
                                <i class="bi bi-person-gear me-2"></i> Configurar Perfil
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item py-2 text-danger fw-bold" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </li>
                </ul>

            </div>
        </div>
    </nav>

    {{-- CONTENIDO PRINCIPAL --}}
    <div class="container mt-4 mb-5">
        {{-- Título de página estilizado --}}
        <div class="d-flex align-items-center mb-4 page-title-border bg-white p-3 rounded shadow-sm">
            <h1 class="h3 mb-0 fw-bold text-dark">@yield('title')</h1>
        </div>

        {{-- Área de contenido (Fondo blanco para limpieza) --}}
        <div class="bg-white p-4 rounded shadow-sm" style="min-height: 400px;">
            @yield('content')
        </div>
    </div>

    {{-- Estilos para la animación del menú --}}
    <style>
        .animate {
            animation-duration: 0.2s;
            -webkit-animation-duration: 0.2s;
            animation-fill-mode: both;
            -webkit-animation-fill-mode: both;
        }

        @keyframes slideIn {
            0% {
                transform: translateY(1rem);
                opacity: 0;
            }

            100% {
                transform: translateY(0rem);
                opacity: 1;
            }
        }

        .slideIn {
            animation-name: slideIn;
            -webkit-animation-name: slideIn;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')

    @include('layouts.partials.aviso-password-aviso')
</body>

</html>
