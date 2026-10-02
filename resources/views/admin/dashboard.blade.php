@extends('layouts.admin')

@section('title', 'Inicio')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
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
            box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .15) !important;
            transform: translateY(-5px);
            transition: all .3s ease;
        }

        /* Ajuste para borde en responsivo */
        @media (min-width: 768px) {
            .border-start-md {
                border-left: 2px solid #e9ecef !important;
            }
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
    </style>

    {{-- Encabezado de la página --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-grid-1x2-fill text-marca-green me-2"></i> Panel de Control
            </h3>
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

    {{-- 1. TARJETAS DE RESUMEN (KPIs) --}}
    <div class="row g-3 mb-4">
        {{-- Total Alumnos --}}
        <div class="col-md-3">
            <a href="{{ route('alumnos.index') }}" class="text-decoration-none">
                <div
                    class="card bg-marca-black text-white shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-yellow fw-bold text-uppercase"
                            style="font-size: 0.8rem; letter-spacing: 1px;">Total Alumnos</h6>
                        <h2 class="my-2 fw-bold display-5">{{ number_format($totalAlumnos) }}</h2>
                        <small class="text-white-50">Registrados en el sistema</small>
                        {{-- <span class="badge bg-white text-dark mt-2 px-3 py-2 rounded-pill shadow-sm">Ver lista completa <i
                                class="bi bi-arrow-right ms-1"></i></span> --}}
                    </div>
                    <i class="bi bi-people-fill kpi-icon-bg text-white"></i>
                </div>
            </a>
        </div>

        {{-- Total Profesores --}}
        <div class="col-md-3">
            <a href="{{ route('admin.reportes.profesores') }}" class="text-decoration-none">
                <div
                    class="card bg-marca-green text-white shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-yellow fw-bold text-uppercase"
                            style="font-size: 0.8rem; letter-spacing: 1px;">Profesores</h6>
                        <h2 class="my-2 fw-bold display-5">{{ number_format($totalProfesores) }}</h2>
                        <small class="text-white-50">En el directorio</small>
                        {{-- <span class="badge bg-white text-marca-green mt-2 px-3 py-2 rounded-pill shadow-sm">Ver directorio <i
                                class="bi bi-arrow-right ms-1"></i></span> --}}
                    </div>
                    <i class="bi bi-person-video3 kpi-icon-bg text-white"></i>
                </div>
            </a>
        </div>

        {{-- Clases Hoy --}}
        <div class="col-md-3">
            <a href="{{ route('admin.reportes.clases-hoy') }}" class="text-decoration-none">
                <div
                    class="card bg-white shadow-sm h-100 border-start border-5 border-marca-yellow rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-muted fw-bold text-uppercase"
                            style="font-size: 0.8rem; letter-spacing: 1px;">Clases de Hoy</h6>
                        <h2 class="my-2 fw-bold display-5 text-marca-black">{{ $clasesHoy }}</h2>
                        {{-- Qué está pasando ahora mismo --}}
                        <small class="text-muted">
                            @if ($motivoInhabilHoy)
                                <i class="bi bi-star-fill text-warning"></i> Día inhábil: {{ $motivoInhabilHoy }}
                            @elseif ($clasesHoy === 0)
                                Sin clases programadas hoy
                            @else
                                @php $enCurso = $clasesDeHoy->where('estado', 'en_curso')->count(); @endphp
                                @if ($enCurso > 0)
                                    <span class="text-marca-green fw-bold"><i class="bi bi-broadcast"></i> {{ $enCurso }} en
                                        curso</span>
                                @endif
                                @if ($siguienteClase)
                                    {{ $enCurso > 0 ? '·' : '' }} Siguiente a las
                                    {{ \Carbon\Carbon::parse($siguienteClase['horario']->hora_inicio)->format('H:i') }}
                                @elseif ($enCurso === 0)
                                    Ya terminaron las de hoy
                                @endif
                            @endif
                        </small>
                        {{-- <span class="badge bg-light text-dark border mt-2 px-3 py-2 rounded-pill shadow-sm">Ver agenda <i
                                class="bi bi-arrow-right ms-1"></i></span> --}}
                    </div>
                    <i class="bi bi-calendar-check kpi-icon-bg text-dark opacity-10"></i>
                </div>
            </a>
        </div>

        {{-- Uso Libre Total --}}
        <div class="col-md-3">
            <a href="{{ route('admin.reportes.uso-libre') }}" class="text-decoration-none">
                <div
                    class="card bg-marca-yellow shadow-sm h-100 border-0 rounded-4 shadow-hover overflow-hidden position-relative">
                    <div class="card-body p-4 z-1">
                        <h6 class="card-title mb-1 text-marca-black fw-bold text-uppercase"
                            style="font-size: 0.8rem; letter-spacing: 1px;">Historial Uso Libre</h6>
                        <h2 class="my-2 fw-bold display-5 text-marca-black">{{ number_format($totalUsoLibre) }}</h2>
                        <small class="text-marca-black opacity-75">
                            En el semestre · <strong>{{ $usoLibreHoy }}</strong> hoy
                        </small>
                        {{-- <span class="badge bg-marca-black text-white mt-2 px-3 py-2 rounded-pill shadow-sm">Ver historial <i
                                class="bi bi-arrow-right ms-1"></i></span> --}}
                    </div>
                    <i class="bi bi-pc-display kpi-icon-bg text-marca-black" style="opacity: 0.1;"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- LOS LABORATORIOS EN ESTE MOMENTO: el mismo bloque del encargado y del Monitor
         (ver App\Support\EstadoDeLaboratorios). Las fallas pendientes, a la mano. --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="fw-bold text-marca-black mb-0"><i class="bi bi-building text-marca-green me-2"></i>Laboratorios en este momento</h5>
        @if ($fallasPendientes > 0)
            <a href="{{ route('admin.incidencias.index') }}" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                {{ $fallasPendientes }} {{ $fallasPendientes == 1 ? 'falla pendiente' : 'fallas pendientes' }}
            </a>
        @else
            <span class="small text-muted"><i class="bi bi-check-circle text-marca-green me-1"></i>Sin fallas pendientes</span>
        @endif
    </div>
    @include('partials.laboratorios-ahora', ['laboratorios' => $laboratorios])

    {{-- 2. RENDIMIENTO SEMANAL: cómo va la semana, clase por clase (ver App\Support\AgendaSemanal) --}}
    <div class="mb-4">
        @include('admin.partials.progreso-semana', ['semana' => $semana])
    </div>

    <div class="row g-4 mb-4">
        {{-- 2. TABLA: Profesores con más "Faltas" --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div
                    class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
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
                                    <th class="text-center rounded-end border-0">Faltas Acumuladas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($profesoresFaltas as $profe)
                                    <tr>
                                        <td class="ps-3 border-bottom border-light py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light text-dark fw-bold rounded-circle d-flex justify-content-center align-items-center me-3 border flex-shrink-0"
                                                    style="width: 35px; height: 35px;">
                                                    {{ Str::upper(Str::substr($profe->user->name ?? 'P', 0, 1)) }}
                                                </div>
                                                {{-- Nombre completo: sólo con el nombre de pila no se distinguía a quién --}}
                                                <div>
                                                    <span class="fw-bold text-dark d-block text-capitalize">
                                                        {{ mb_strtolower(trim(($profe->user->name ?? '') . ' ' . ($profe->user->apellido_paterno ?? '') . ' ' . ($profe->user->apellido_materno ?? ''))) }}
                                                    </span>
                                                    @if (!empty($profe->user->username))
                                                        <small class="text-muted">{{ $profe->user->username }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center border-bottom border-light py-3">
                                            <span
                                                class="badge bg-danger bg-opacity-10 text-danger border border-danger fs-6 px-3 rounded-pill">
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

        {{-- 3. TABLA: Actividad Reciente (Uso Libre) --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div
                    class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-marca-black text-uppercase"><i
                            class="bi bi-clock-history text-marca-green me-2"></i> Ultima Actividad de Uso Libre</h6>
                    {{-- <a href="{{ route('admin.reportes.uso-libre') }}"
                        class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size: 0.75rem;">Ver Todos</a> --}}
                </div>
                <div class="card-body p-0 px-3 pb-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3 rounded-start border-0">Alumno</th>
                                    <th class="border-0">Laboratorio</th>
                                    <th class="rounded-end border-0">Entrada</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ultimosUsoLibre as $uso)
                                    <tr>
                                        <td class="ps-3 border-bottom border-light py-3">
                                            {{-- NOMBRE COMPLETO --}}
                                            <div class="fw-bold text-dark text-capitalize" style="font-size: 0.9rem;">
                                                {{ mb_strtolower($uso->user->name ?? '') }}
                                                {{ mb_strtolower($uso->user->apellido_paterno ?? '') }}
                                                {{ mb_strtolower($uso->user->apellido_materno ?? '') }}
                                            </div>
                                            <small class="text-muted"><i class="bi bi-person-badge"></i>
                                                {{ $uso->user->matricula }}</small>
                                        </td>
                                        <td class="border-bottom border-light py-3">
                                            <span class="fw-bold text-dark small">
                                                {{ $uso->centroComputo->nombre_centro ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="border-bottom border-light py-3">
                                            {{-- FECHA Y HORA EXACTA --}}
                                            <div class="small fw-bold text-dark">
                                                {{ \Carbon\Carbon::parse($uso->fecha_hora_registro)->format('d/m/Y') }}
                                            </div>
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-clock text-marca-green"></i>
                                                {{ \Carbon\Carbon::parse($uso->fecha_hora_registro)->format('h:i A') }}
                                            </small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted border-0">
                                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                            <span class="d-block fw-bold text-dark">Bandeja Vacía</span>
                                            <small>No hay registros de uso libre recientes.</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
