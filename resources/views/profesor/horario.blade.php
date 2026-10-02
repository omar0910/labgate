@extends('layouts.profesor')
@section('title', 'Mi Horario Semanal')

@section('content')

    {{-- ESTILOS INSTITUCIONALES Y DE HORARIO --}}
    <style>
        /* Estilos específicos de los días */
        .day-card {
            border: none;
            border-radius: 1rem;
            overflow: hidden;
            background-color: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .day-header {
            background-color: var(--marca-green);
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .class-item {
            border-left: 4px solid var(--marca-yellow);
            background-color: #f8f9fa;
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .class-item:hover {
            transform: translateX(5px);
            background-color: #ffffff;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08);
        }

        .class-item:last-child {
            margin-bottom: 0;
        }

        /* El día de hoy resalta (amarillo institucional) */
        .day-card.es-hoy {
            box-shadow: 0 0 0 3px var(--marca-yellow), 0 .5rem 1rem rgba(0, 0, 0, .08);
        }

        .day-card.es-hoy .day-header {
            background-color: var(--marca-black);
            color: var(--marca-yellow);
        }

        /* Una clase especial (sólo esa fecha) se distingue de las fijas */
        .class-item.clase-especial {
            border-left-color: var(--marca-black);
            background-color: #fffbe0;
        }

        /* Los nombres de grupo suelen ser largos: se acomodan en varias líneas en
           lugar de salirse de la tarjeta */
        .grupo-clase {
            white-space: normal;
            text-align: left;
            line-height: 1.3;
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-calendar3 text-marca-green me-2"></i> Mi Horario Semanal
                </h3>
                <p class="text-muted small mb-0 mt-1">
                    Visualiza todas tus clases programadas de la semana.
                </p>
            </div>
            {{--
            <div class="mt-3 mt-md-0">
                <a href="{{ route('profesor.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>--}}
        </div>

        {{-- CUADRÍCULA DEL CALENDARIO --}}
        <div class="row g-4 mt-2">
            @foreach ($diasSemana as $dia)
                <div class="col-md-6 col-lg-4">

                    {{-- TARJETA DEL DÍA --}}
                    <div class="card day-card h-100 {{ $dia === $diaHoy ? 'es-hoy' : '' }}">
                        <div class="day-header">
                            {{ $dia }}
                            @if ($dia === $diaHoy)
                                <span class="badge bg-marca-yellow text-dark ms-1 align-middle">HOY</span>
                            @endif
                        </div>
                        <div class="card-body p-4">

                            {{-- Si hay clases este día (fijas o una reserva especial de esta semana), las mostramos --}}
                            @if ((isset($horarioPorDia[$dia]) && $horarioPorDia[$dia]->count() > 0) || isset($especialesPorDia[$dia]))
                                @foreach ($especialesPorDia[$dia] ?? [] as $clase)
                                    <div class="class-item clase-especial shadow-sm">
                                        <span class="badge bg-marca-yellow text-dark mb-2">
                                            <i class="bi bi-star-fill me-1"></i> Clase especial ·
                                            {{ \Carbon\Carbon::parse($clase->fecha_especial)->translatedFormat('d \d\e F') }}
                                        </span>
                                        <div class="fw-bold text-marca-black mb-2" style="line-height: 1.2;">
                                            {{ $clase->materia->nombre_materia ?? 'Clase' }}
                                        </div>
                                        <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                                            <span class="badge bg-marca-green text-white shadow-sm px-2 py-1 flex-shrink-0">
                                                <i class="bi bi-clock"></i>
                                                {{ date('H:i', strtotime($clase->hora_inicio)) }} -
                                                {{ date('H:i', strtotime($clase->hora_fin)) }}
                                            </span>
                                            <span class="badge bg-light text-dark border px-2 py-1 grupo-clase mw-100">
                                                <i class="bi bi-people-fill text-secondary"></i>
                                                {{ $clase->grupo->nombre_grupo ?? 'Sin grupo' }}
                                            </span>
                                        </div>
                                        <div class="text-muted small mt-2">
                                            <i class="bi bi-pc-display text-marca-green me-1"></i>
                                            {{ $clase->centroComputo->nombre_centro ?? '' }}
                                        </div>
                                    </div>
                                @endforeach

                                @foreach ($horarioPorDia[$dia] ?? [] as $clase)
                                    <div class="class-item shadow-sm border-1 border-light">
                                        <div class="fw-bold text-marca-black mb-2" style="line-height: 1.2;">
                                            {{ $clase->materia->nombre_materia }}
                                        </div>

                                        <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                                            <span class="badge bg-marca-green text-white shadow-sm px-2 py-1 flex-shrink-0">
                                                <i class="bi bi-clock"></i>
                                                {{ date('H:i', strtotime($clase->hora_inicio)) }} -
                                                {{ date('H:i', strtotime($clase->hora_fin)) }}
                                            </span>
                                            <span class="badge bg-light text-dark border px-2 py-1 grupo-clase mw-100">
                                                <i class="bi bi-people-fill text-secondary"></i>
                                                {{ $clase->grupo->nombre_grupo ?? 'Sin grupo' }}
                                            </span>
                                        </div>

                                        <div class="text-muted small mt-2">
                                            <i class="bi bi-pc-display text-marca-green me-1"></i>
                                            {{ $clase->centroComputo->nombre_centro }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                {{-- Si el día está vacío --}}
                                <div class="text-center text-muted py-5 opacity-50">
                                    <i class="bi bi-cup-hot fs-1 d-block mb-3"></i>
                                    <span class="fw-bold d-block">Día libre</span>
                                    <small>Sin laboratorios</small>
                                </div>
                            @endif

                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        {{-- RESERVAS ESPECIALES: clases en una fecha concreta, fuera del horario fijo --}}
        @if ($especiales->isNotEmpty())
            <div class="card shadow-sm border-0 rounded-4 mt-5 overflow-hidden">
                <div class="card-header bg-marca-black border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold text-white">
                        <i class="bi bi-star-fill text-marca-yellow me-2"></i> Próximas clases especiales
                        <span class="badge bg-marca-yellow text-dark ms-1">{{ $especiales->count() }}</span>
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($especiales as $clase)
                        @php $fecha = \Carbon\Carbon::parse($clase->fecha_especial); @endphp
                        <div class="list-group-item px-4 py-3 d-flex flex-wrap align-items-center gap-3">
                            <div class="text-center flex-shrink-0 bg-light border rounded-3 py-1" style="width: 58px;">
                                <span class="d-block text-muted fw-bold text-uppercase"
                                    style="font-size: 0.65rem;">{{ rtrim($fecha->translatedFormat('D'), '.') }}</span>
                                <span class="d-block fw-bold text-dark lh-1">{{ $fecha->format('d') }}</span>
                                <span class="d-block text-muted" style="font-size: 0.7rem;">{{ rtrim($fecha->translatedFormat('M'), '.') }}</span>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="fw-bold text-marca-black">
                                    {{ $clase->materia->nombre_materia ?? 'Clase' }}
                                    @if ($fecha->isToday())
                                        <span class="badge bg-marca-yellow text-dark ms-1">HOY</span>
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-people me-1"></i>{{ $clase->grupo->nombre_grupo ?? 'Sin grupo' }}
                                </div>
                            </div>
                            <div class="small text-muted text-nowrap">
                                <i class="bi bi-clock text-marca-green me-1"></i>{{ date('H:i', strtotime($clase->hora_inicio)) }}
                                - {{ date('H:i', strtotime($clase->hora_fin)) }}
                                @if ($clase->centroComputo)
                                    · <i class="bi bi-pc-display text-marca-green me-1"></i>{{ $clase->centroComputo->nombre_centro }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

@endsection
