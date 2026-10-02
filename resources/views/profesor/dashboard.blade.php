@extends('layouts.profesor')
@section('title', 'Mis Clases de Hoy')

@section('content')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .shadow-hover {
            transition: all 0.3s ease;
        }

        .shadow-hover:hover {
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
            transform: translateY(-3px);
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black">
                    <i class=""></i> ¡Hola, Prof. {{ Auth::user()->name }}!
                </h3>
                <p class="text-muted small mb-0 mt-1">
                    Tus clases programadas para hoy,
                    <strong class="text-marca-green">
                        {{ ucfirst(\Carbon\Carbon::now()->translatedFormat('l, d \d\e F \d\e Y')) }}
                    </strong>.
                </p>
            </div>

        </div>

        {{-- Clases que ya pasaron y se quedaron sin ninguna asistencia --}}
        @if (($sinLista ?? 0) > 0)
            <div class="alert rounded-4 shadow-sm border-0 border-start border-4 border-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2"
                style="background-color: #fffbe0;">
                <div>
                    <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                    Tienes <strong>{{ $sinLista }} {{ $sinLista == 1 ? 'clase pasada' : 'clases pasadas' }} sin lista</strong>
                    en este periodo: nadie quedó registrado.
                </div>
                <a href="{{ route('profesor.historial', ['estado' => 'sin_lista']) }}"
                    class="btn btn-sm btn-marca-black rounded-pill px-3 fw-bold text-nowrap">
                    Ver cuáles <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        @endif

        {{-- CUADRÍCULA DE CLASES --}}
        <div class="row g-4">
            @forelse ($clasesHoy as $clase)
                <div class="col-md-6 col-xl-4">
                    {{-- TARJETA DE CLASE --}}
                    <div class="card h-100 shadow-sm border-0 rounded-4 shadow-hover position-relative overflow-hidden">

                        {{-- Franja de color superior --}}
                        <div class="position-absolute top-0 start-0 w-100 bg-marca-green" style="height: 5px;"></div>

                        <div class="card-body p-4 d-flex flex-column">

                            {{-- Badges de Laboratorio y Hora --}}
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 shadow-sm">
                                    <i class="bi bi-pc-display text-marca-green me-1"></i>
                                    {{ $clase->centroComputo->nombre_centro }}
                                </span>
                                <span class="badge bg-marca-green rounded-pill px-3 py-2 shadow-sm">
                                    <i class="bi bi-clock me-1"></i>
                                    {{ date('H:i', strtotime($clase->hora_inicio)) }} -
                                    {{ date('H:i', strtotime($clase->hora_fin)) }}
                                </span>
                            </div>

                            {{-- Información de la Materia --}}
                            <div class="mb-3 flex-grow-1">
                                <h5 class="card-title fw-bold text-marca-black mb-2">{{ $clase->materia->nombre_materia }}
                                </h5>
                                <p class="card-text text-muted mb-0">
                                    <span class="badge bg-light text-dark border text-wrap text-start"><i
                                            class="bi bi-people-fill text-secondary me-1"></i> Grupo:
                                        {{ $clase->grupo->nombre_grupo }}</span>
                                </p>
                            </div>

                            {{-- Cómo va: si ya empezó y cuántos se han registrado --}}
                            <div class="d-flex justify-content-between align-items-center small mb-3 bg-light rounded-3 px-3 py-2">
                                @if ($clase->momento === 'en_curso')
                                    <span class="fw-bold text-marca-green">
                                        <span class="spinner-grow spinner-grow-sm text-success me-1" style="width: .6rem; height: .6rem;"></span>
                                        En curso
                                    </span>
                                @elseif ($clase->momento === 'antes')
                                    <span class="fw-bold text-muted"><i class="bi bi-hourglass-split me-1"></i> Por empezar</span>
                                @else
                                    <span class="fw-bold text-muted"><i class="bi bi-flag me-1"></i> Terminó</span>
                                @endif
                                <span class="text-muted">
                                    <strong class="text-dark">{{ $clase->registrados }}</strong> de {{ $clase->total_alumnos }}
                                    registrados
                                </span>
                            </div>

                            {{-- Botón de Acción --}}
                            <a href="{{ route('profesor.revisar-clase', ['horario_id' => $clase->id]) }}"
                                class="btn btn-marca-green w-100 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center py-2 mt-auto">
                                <i class="bi bi-card-checklist fs-5 me-2"></i>
                                {{ $clase->total_alumnos > 0 && $clase->registrados >= $clase->total_alumnos ? 'Ver Lista' : 'Revisar Asistencia' }}
                            </a>

                        </div>
                    </div>
                </div>
            @empty
                {{-- ESTADO VACÍO --}}
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-4 bg-light">
                        <div class="card-body text-center py-5">
                            @if ($fueraDePeriodo)
                                {{-- Hoy no cae dentro del semestre activo --}}
                                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm"
                                    style="width: 80px; height: 80px;">
                                    <i class="bi bi-calendar-x fs-1 text-marca-yellow"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-2">Fuera del periodo de clases</h4>
                                @if ($semestreActivo)
                                    <p class="text-muted mb-0">
                                        El semestre activo es
                                        <strong class="text-marca-green">{{ $semestreActivo->nombre }}</strong>
                                        ({{ \Carbon\Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') }}
                                        al {{ \Carbon\Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') }})
                                        y la fecha de hoy queda fuera de ese rango.
                                    </p>
                                @else
                                    <p class="text-muted mb-0">
                                        No hay ningún semestre marcado como activo en el sistema.
                                        Pide al administrador que active el periodo correspondiente.
                                    </p>
                                @endif
                            @else
                                {{-- Sí estamos en semestre, pero hoy no le tocan clases --}}
                                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm"
                                    style="width: 80px; height: 80px;">
                                    <i class="bi bi-emoji-smile fs-1 text-marca-green"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-2">¡Día libre de laboratorios!</h4>
                                <p class="text-muted mb-0">No tienes clases programadas en centros de cómputo para el día de
                                    hoy.</p>

                                {{-- Qué sigue, y dónde pasar la lista de una clase que ya pasó --}}
                                @if ($proximaClase)
                                    <p class="text-dark small mt-3 mb-0">
                                        <i class="bi bi-calendar-event text-marca-green me-1"></i>
                                        Tu próxima clase:
                                        <strong>{{ $proximaClase['horario']->materia->nombre_materia ?? 'Clase' }}</strong>,
                                        {{ $proximaClase['fecha']->translatedFormat('l d \d\e F') }}
                                        a las {{ \Carbon\Carbon::parse($proximaClase['horario']->hora_inicio)->format('H:i') }}
                                        @if ($proximaClase['horario']->centroComputo)
                                            en {{ $proximaClase['horario']->centroComputo->nombre_centro }}
                                        @endif
                                    </p>
                                @endif
                                <a href="{{ route('profesor.historial') }}"
                                    class="btn btn-sm btn-outline-secondary rounded-pill px-3 mt-3">
                                    <i class="bi bi-clock-history me-1"></i> Ver mis clases anteriores
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

    </div>
@endsection
