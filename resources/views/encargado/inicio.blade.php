@extends('layouts.encargado')

@section('title', 'Inicio')

@section('content')

    {{-- Mismos estilos que el inicio del administrador --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .15) !important;
            transform: translateY(-5px);
            transition: all .3s ease;
        }

        /* Iconos de fondo en KPIs */
        .kpi-icon-bg {
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 5rem;
            opacity: 0.15;
            transform: rotate(-10deg);
            transition: all .3s ease;
        }

        .card:hover .kpi-icon-bg {
            transform: rotate(0deg) scale(1.1);
            opacity: 0.25;
        }

        /* La tarjeta de cada laboratorio tiene su estilo en partials/laboratorios-ahora */
    </style>

    {{-- Encabezado de la página --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-grid-1x2-fill text-marca-green me-2"></i> Panel del
                Encargado</h3>
            <p class="text-muted small mb-0 mt-1">
                @if ($semestreActivo)
                    Semestre <span class="fw-bold text-marca-green">{{ $semestreActivo->nombre }}</span>
                    · del {{ \Carbon\Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') }}
                    al {{ \Carbon\Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') }}
                @else
                    <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> No hay semestre activo
                        configurado.</span>
                @endif
            </p>
        </div>
        <span class="badge bg-white text-marca-black border shadow-sm rounded-pill px-3 py-2 fw-semibold align-self-md-center">
            <i class="bi bi-calendar-event text-marca-green me-1"></i>
            {{ ucfirst(\Carbon\Carbon::now()->translatedFormat('l d \d\e F \d\e Y')) }}
        </span>
    </div>

    {{-- 1. TARJETAS DE RESUMEN --}}
    <div class="row g-3 mb-4">
        {{-- Clases de hoy --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('encargado.dashboard') }}" class="text-decoration-none">
                <div class="card bg-marca-black text-white shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-yellow fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                            Clases de Hoy</h6>
                        <h2 class="my-2 fw-bold display-5">{{ $clasesDeHoy->count() }}</h2>
                        <small class="text-white-50">
                            @if ($motivoInhabilHoy)
                                <i class="bi bi-star-fill text-warning"></i> Día inhábil: {{ $motivoInhabilHoy }}
                            @elseif ($clasesDeHoy->isEmpty())
                                Sin clases programadas hoy
                            @else
                                @if ($clasesAhora->isNotEmpty())
                                    <span class="text-marca-yellow fw-bold"><i class="bi bi-broadcast"></i> {{ $clasesAhora->count() }} en curso</span>
                                @endif
                                @if ($siguienteClase)
                                    {{ $clasesAhora->isNotEmpty() ? '·' : '' }} Siguiente a las
                                    {{ \Carbon\Carbon::parse($siguienteClase['horario']->hora_inicio)->format('H:i') }}
                                @elseif ($clasesAhora->isEmpty())
                                    Ya terminaron las de hoy
                                @endif
                            @endif
                        </small>
                    </div>
                    <i class="bi bi-calendar-check kpi-icon-bg text-white"></i>
                </div>
            </a>
        </div>

        {{-- Laboratorios ahora --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('monitor.index') }}" class="text-decoration-none">
                <div class="card bg-marca-green text-white shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-yellow fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                            PCs en uso ahora</h6>
                        <h2 class="my-2 fw-bold display-5">{{ $pcsOcupadas }}<span class="fs-4 text-white-50"> / {{ $pcsTotales }}</span></h2>
                        <small class="text-white-50">En todos los laboratorios</small>
                    </div>
                    <i class="bi bi-pc-display kpi-icon-bg text-white"></i>
                </div>
            </a>
        </div>

        {{-- Uso libre hoy --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('encargado.uso-libre') }}" class="text-decoration-none">
                <div class="card bg-marca-yellow shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-black fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                            Uso Libre Hoy</h6>
                        <h2 class="my-2 fw-bold display-5 text-marca-black">{{ $usoLibreHoy }}</h2>
                        <small class="text-marca-black opacity-75">
                            <strong>{{ $usoLibreAhora->count() }}</strong> {{ $usoLibreAhora->count() == 1 ? 'alumno sigue' : 'alumnos siguen' }} dentro
                        </small>
                    </div>
                    <i class="bi bi-laptop kpi-icon-bg text-marca-black" style="opacity: 0.1;"></i>
                </div>
            </a>
        </div>

        {{-- Fallas pendientes --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('encargado.incidencias.index') }}" class="text-decoration-none">
                <div class="card bg-white shadow-sm h-100 border-start border-5 border-marca-yellow rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-muted fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                            Fallas Pendientes</h6>
                        <h2 class="my-2 fw-bold display-5 {{ $fallasPendientes > 0 ? 'text-danger' : 'text-marca-black' }}">{{ $fallasPendientes }}</h2>
                        <small class="text-muted">
                            <strong class="text-marca-black">{{ $pcsMantenimiento }}</strong>
                            {{ $pcsMantenimiento == 1 ? 'PC en mantenimiento' : 'PCs en mantenimiento' }}
                        </small>
                    </div>
                    <i class="bi bi-tools kpi-icon-bg text-dark" style="opacity: 0.1;"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- 2. LOS LABORATORIOS EN ESTE MOMENTO --}}
    <h5 class="fw-bold text-marca-black mb-3"><i class="bi bi-building text-marca-green me-2"></i>Laboratorios en este momento</h5>
    {{-- La misma tarjeta del admin y del Monitor (ver App\Support\EstadoDeLaboratorios) --}}
    @include('partials.laboratorios-ahora', ['laboratorios' => $laboratorios])

    {{-- 3. RENDIMIENTO SEMANAL (la misma tarjeta del administrador) --}}
    <div class="mb-4">
        @include('admin.partials.progreso-semana', ['semana' => $semana])
    </div>

    {{-- 4. PENDIENTES POR ATENDER --}}
    <div class="row g-4 mb-4">
        {{-- Clases por registrar --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-marca-black text-uppercase"><i
                            class="bi bi-hourglass-split text-marca-green me-2"></i> Por registrar esta semana</h6>
                    <a href="{{ route('admin.bitacora.pendientes', ['origen' => 'inicio']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        Clases pendientes <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    @forelse ($porRegistrar->take(6) as $clase)
                        <a href="{{ route('encargado.dashboard', ['fecha' => $clase['fecha']]) }}"
                            class="d-flex justify-content-between align-items-center py-2 border-bottom border-light text-decoration-none">
                            <div>
                                <div class="fw-bold text-dark small">{{ $clase['horario']->materia->nombre_materia ?? 'Clase' }}</div>
                                <small class="text-muted">
                                    {{ trim(($clase['horario']->user->name ?? '') . ' ' . ($clase['horario']->user->apellido_paterno ?? '')) }}
                                    · {{ $clase['horario']->centroComputo->nombre_centro ?? '' }}
                                </small>
                            </div>
                            <span class="badge bg-marca-yellow text-marca-black rounded-pill text-nowrap">
                                {{ ucfirst(\Carbon\Carbon::parse($clase['fecha'])->translatedFormat('D d')) }}
                                {{ \Carbon\Carbon::parse($clase['horario']->hora_inicio)->format('H:i') }}
                            </span>
                        </a>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check2-circle text-success fs-1 d-block mb-2 opacity-75"></i>
                            <span class="d-block fw-bold text-dark">Todo al día</span>
                            <small>No hay clases de esta semana sin el registro del profesor.</small>
                        </div>
                    @endforelse
                    @if ($porRegistrar->count() > 6)
                        <a href="{{ route('encargado.semana', ['estado' => 'por_registrar']) }}" class="small text-marca-green fw-bold d-block mt-2">
                            Ver las {{ $porRegistrar->count() }} de esta semana <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Últimas fallas reportadas --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-marca-black text-uppercase"><i
                            class="bi bi-exclamation-octagon-fill text-danger me-2"></i> Fallas pendientes</h6>
                    <a href="{{ route('encargado.incidencias.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        Reporte Fallas <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    @forelse ($ultimasFallas as $falla)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <div>
                                <div class="fw-bold text-dark small">
                                    {{ $falla->centroComputo->nombre_centro ?? 'Laboratorio' }} · PC #{{ $falla->numero_maquina }}
                                </div>
                                <small class="text-muted">{{ $falla->categoria }}</small>
                            </div>
                            <small class="text-muted text-nowrap">{{ $falla->created_at?->diffForHumans() }}</small>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-shield-check text-success fs-1 d-block mb-2 opacity-75"></i>
                            <span class="d-block fw-bold text-dark">Sin fallas pendientes</span>
                            <small>Todas las computadoras reportadas ya se atendieron.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- 5. FALTAS DE PROFESORES Y USO LIBRE --}}
    <div class="row g-4 mb-4">
        {{-- Faltas de profesores (el mismo ranking del administrador) --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-marca-black text-uppercase"><i
                            class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Faltas de profesores</h6>
                    <span class="badge bg-danger rounded-pill">Top 5</span>
                </div>
                <div class="card-body p-0 px-3 pb-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3 rounded-start border-0">Profesor</th>
                                    <th class="text-center rounded-end border-0">Faltas en el semestre</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($profesoresFaltas as $profe)
                                    <tr>
                                        <td class="ps-3 border-bottom border-light py-3">
                                            <span class="fw-bold text-dark text-capitalize">
                                                {{ mb_strtolower(trim(($profe->user->name ?? '') . ' ' . ($profe->user->apellido_paterno ?? '') . ' ' . ($profe->user->apellido_materno ?? ''))) }}
                                            </span>
                                        </td>
                                        <td class="text-center border-bottom border-light py-3">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger fs-6 px-3 rounded-pill">
                                                {{ $profe->total_faltas }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-5 text-muted border-0">
                                            <i class="bi bi-shield-check text-success fs-1 d-block mb-3 opacity-75"></i>
                                            <span class="d-block fw-bold text-dark">¡Excelente asistencia!</span>
                                            <small>Ningún profesor ha registrado faltas en este semestre.</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Uso libre: quién está ahora y lo más reciente --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-marca-black text-uppercase"><i
                            class="bi bi-laptop text-marca-green me-2"></i> Uso libre</h6>
                    <a href="{{ route('encargado.uso-libre') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        Ver historial <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1">En este momento</small>
                    @forelse ($usoLibreAhora as $uso)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <div>
                                <div class="fw-bold text-dark small text-capitalize">
                                    {{ mb_strtolower(trim(($uso->user->name ?? '') . ' ' . ($uso->user->apellido_paterno ?? ''))) }}
                                </div>
                                <small class="text-muted">{{ $uso->centroComputo->nombre_centro ?? 'N/A' }} · PC #{{ $uso->numero_maquina }}</small>
                            </div>
                            <small class="text-marca-green fw-bold text-nowrap">
                                <i class="bi bi-box-arrow-in-right"></i>
                                desde {{ \Carbon\Carbon::parse($uso->fecha_hora_registro)->format('h:i A') }}
                            </small>
                        </div>
                    @empty
                        <p class="small text-muted mb-3">Nadie está en uso libre ahora.</p>
                    @endforelse

                    <small class="text-muted text-uppercase fw-bold d-block mt-3 mb-1">Últimos registros</small>
                    @forelse ($ultimosUsoLibre as $uso)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <div class="small text-dark text-capitalize">
                                {{ mb_strtolower(trim(($uso->user->name ?? '') . ' ' . ($uso->user->apellido_paterno ?? ''))) }}
                                <span class="text-muted">· {{ $uso->centroComputo->nombre_centro ?? 'N/A' }}</span>
                            </div>
                            <small class="text-muted text-nowrap">
                                {{ \Carbon\Carbon::parse($uso->fecha_hora_registro)->format('d/m h:i A') }}
                            </small>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">No hay registros de uso libre en el semestre.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@endsection
