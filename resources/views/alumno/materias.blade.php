@extends('layouts.alumnos')
@section('title', 'Mi Progreso de Asistencia')

@section('content')
    @include('partials.estilos-progreso')

    @php
        $minimo = \App\Support\ProgresoDelAlumno::MINIMO;
        $enRiesgo = collect($resumen)->where('porcentaje', '<', $minimo)->count();
    @endphp

    <div class="row g-4">
        {{-- 1. ENCABEZADO PRINCIPAL (Estilo Dashboard) --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border"
                            style="width: 55px; height: 55px;">
                            <i class="bi bi-bar-chart-steps fs-3" style="color: #009B4D;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Resumen de Asistencia por Materia</h4>
                            <p class="text-muted small mb-0 mt-1">Monitorea tu nivel de cumplimiento y progreso en cada una
                                de tus asignaturas.</p>
                        </div>
                    </div>

                    {{-- SELECTOR DE SEMESTRE (Diseño Píldora Premium) --}}
                    <form action="{{ route('alumno.materias') }}" method="GET"
                        class="d-flex align-items-center bg-light p-2 rounded-pill border shadow-sm">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm ms-1 me-2"
                            style="width: 30px; height: 30px;">
                            <i class="bi bi-calendar3 text-marca-green" style="font-size: 0.85rem;"></i>
                        </div>
                        <label class="fw-bold text-muted small me-2 text-uppercase mb-0"
                            style="letter-spacing: 0.5px; font-size: 0.7rem;">Periodo:</label>
                        <select name="semestre_id"
                            class="form-select form-select-sm bg-transparent border-0 fw-bold text-dark shadow-none pe-4"
                            style="min-width: 220px; cursor: pointer;" onchange="this.form.submit()">
                            @foreach ($semestres as $sem)
                                <option value="{{ $sem->id }}" {{ $semestreId == $sem->id ? 'selected' : '' }}>
                                    {{ $sem->nombre }} {{ $sem->es_activo ? '(Activo)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>

        {{-- 2. RESUMEN GENERAL DEL PERIODO --}}
        @if (count($resumen) > 0)
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="anillo-progreso {{ $general['porcentaje'] < $minimo ? 'en-riesgo' : '' }} mx-auto mx-md-0"
                            style="--valor: {{ $general['porcentaje'] }};">
                            <span>{{ $general['porcentaje'] }}%</span>
                        </div>

                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h5 class="fw-bold text-dark mb-0">Tu asistencia en el periodo</h5>
                                @if ($enRiesgo > 0)
                                    <span class="estado-clase falta">
                                        <i class="bi bi-shield-exclamation"></i>
                                        {{ $enRiesgo }} {{ $enRiesgo == 1 ? 'materia' : 'materias' }} en riesgo
                                    </span>
                                @else
                                    <span class="estado-clase asistio">
                                        <i class="bi bi-shield-check"></i> Todas tus materias al día
                                    </span>
                                @endif
                            </div>
                            <p class="text-muted small mb-3">
                                Cada materia necesita al menos <strong class="text-dark">{{ $minimo }}%</strong> de
                                asistencia para estar <strong class="text-dark">Regular</strong>. Presiona una materia
                                para ver clase por clase.
                            </p>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                    <strong>{{ $general['total'] }}</strong> {{ $general['total'] == 1 ? 'clase' : 'clases' }}
                                </span>
                                <span class="badge rounded-pill px-3 py-2 estado-clase asistio">
                                    <strong>{{ $general['asistencias'] }}</strong>
                                    {{ $general['asistencias'] == 1 ? 'asistencia' : 'asistencias' }}
                                </span>
                                <span class="badge rounded-pill px-3 py-2 estado-clase justificada">
                                    <strong>{{ $general['justificadas'] }}</strong>
                                    {{ $general['justificadas'] == 1 ? 'justificada' : 'justificadas' }}
                                </span>
                                <span class="badge rounded-pill px-3 py-2 estado-clase falta">
                                    <strong>{{ $general['faltas'] }}</strong> {{ $general['faltas'] == 1 ? 'falta' : 'faltas' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- 3. TARJETAS DE MATERIAS (cada una abre su detalle) --}}
        @forelse ($resumen as $dato)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 {{ $dato['materia_id'] ? 'hover-lift' : '' }}">
                    <div class="card-body p-4 d-flex flex-column">

                        {{-- Título y Etiqueta de Estado --}}
                        <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                            <h6 class="fw-bold text-dark mb-0 line-clamp-2" style="line-height: 1.4;"
                                title="{{ $dato['materia'] }}">
                                @if ($dato['materia_id'])
                                    <a href="{{ route('alumno.materias.detalle', ['materia' => $dato['materia_id'], 'semestre_id' => $semestreId]) }}"
                                        class="stretched-link text-dark text-decoration-none">{{ $dato['materia'] }}</a>
                                @else
                                    {{ $dato['materia'] }}
                                @endif
                            </h6>
                            <div>
                                @if ($dato['porcentaje'] >= $minimo)
                                    <span
                                        class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                                        <i class="bi bi-shield-check me-1"></i> Regular
                                    </span>
                                @else
                                    <span
                                        class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                                        <i class="bi bi-shield-exclamation me-1"></i> Riesgo
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Barra de Progreso Minimalista --}}
                        <div class="mb-4 mt-2">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <span class="text-muted small fw-bold text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 0.7rem;">Cumplimiento</span>
                                <span class="fw-bold fs-4 {{ $dato['porcentaje'] >= $minimo ? 'text-success' : 'text-danger' }}"
                                    style="line-height: 1;">
                                    {{ $dato['porcentaje'] }}%
                                </span>
                            </div>
                            <div class="progress rounded-pill shadow-sm" style="height: 8px; background-color: #e9ecef;">
                                <div class="progress-bar rounded-pill {{ $dato['porcentaje'] >= $minimo ? 'bg-success' : 'bg-danger' }}"
                                    role="progressbar"
                                    style="width: {{ $dato['porcentaje'] }}%; {{ $dato['porcentaje'] >= $minimo ? 'background-color: #009B4D !important;' : '' }}"
                                    aria-valuenow="{{ $dato['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>

                        {{-- Desglose Numérico en Cajas (Fila Única 1x4) --}}
                        <div class="row g-1 text-center">
                            {{-- Total --}}
                            <div class="col-3">
                                <div
                                    class="p-1 py-2 bg-light border rounded-3 h-100 d-flex flex-column justify-content-center">
                                    <span class="d-block fw-bold text-dark fs-5">{{ $dato['total'] }}</span>
                                    <span class="text-muted fw-bold"
                                        style="font-size: 0.6rem; text-transform: uppercase;">Total</span>
                                </div>
                            </div>

                            {{-- Asistencias --}}
                            <div class="col-3">
                                <div
                                    class="p-1 py-2 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 h-100 d-flex flex-column justify-content-center">
                                    <span class="d-block fw-bold text-success fs-5">{{ $dato['asistencias'] }}</span>
                                    <span class="text-success fw-bold"
                                        style="font-size: 0.6rem; text-transform: uppercase;">Asist.</span>
                                </div>
                            </div>

                            {{-- Justificadas --}}
                            <div class="col-3">
                                <div
                                    class="p-1 py-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 h-100 d-flex flex-column justify-content-center">
                                    <span class="d-block fw-bold fs-5"
                                        style="color: #d39e00;">{{ $dato['justificadas'] }}</span>
                                    <span class="fw-bold"
                                        style="color: #d39e00; font-size: 0.6rem; text-transform: uppercase;">Justif.</span>
                                </div>
                            </div>

                            {{-- Faltas --}}
                            <div class="col-3">
                                <div
                                    class="p-1 py-2 border rounded-3 h-100 d-flex flex-column justify-content-center {{ $dato['faltas'] > 0 ? 'bg-danger bg-opacity-10 border-danger border-opacity-25' : 'bg-light' }}">
                                    <span
                                        class="d-block fw-bold fs-5 {{ $dato['faltas'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $dato['faltas'] }}</span>
                                    <span class="fw-bold {{ $dato['faltas'] > 0 ? 'text-danger' : 'text-muted' }}"
                                        style="font-size: 0.6rem; text-transform: uppercase;">Faltas</span>
                                </div>
                            </div>
                        </div>

                        {{-- Para que sepa de dónde salen las faltas que no marcó nadie --}}
                        @if (($dato['sin_registro'] ?? 0) > 0)
                            <p class="small text-muted mb-0 mt-2">
                                <i class="bi bi-info-circle text-marca-green me-1"></i>
                                Incluye {{ $dato['sin_registro'] }}
                                {{ $dato['sin_registro'] == 1 ? 'clase' : 'clases' }} en las que no te registraste
                                y tus compañeros sí.
                            </p>
                        @endif

                        {{-- Sus últimas clases de un vistazo, y la entrada al detalle --}}
                        <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top gap-2"
                            style="margin-top: 1rem !important;">
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="text-muted fw-bold me-1"
                                    style="font-size: 0.6rem; text-transform: uppercase;">Últimas</span>
                                @foreach ($dato['ultimas'] as $clase)
                                    @php $estado = \App\Support\ProgresoDelAlumno::estado($clase['estado']); @endphp
                                    <span class="punto-clase {{ $estado['clase'] }}"
                                        title="{{ ucfirst(\Carbon\Carbon::parse($clase['fecha'])->translatedFormat('D d/m')) }}: {{ $estado['texto'] }}"></span>
                                @endforeach
                            </div>
                            @if ($dato['materia_id'])
                                <span class="small fw-bold text-marca-green text-nowrap">
                                    Ver detalle <i class="bi bi-arrow-right"></i>
                                </span>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        @empty
            {{-- 4. ESTADO VACÍO (Empty State Profesional) --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3"
                            style="width: 80px; height: 80px;">
                            <i class="bi bi-clipboard-x fs-1 text-muted opacity-50"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Aún no hay registros académicos</h4>
                        <p class="text-muted mx-auto" style="max-width: 500px;">
                            No hemos encontrado datos de asistencia para este periodo. En cuanto comiences a registrar tus
                            entradas a los laboratorios, tu progreso aparecerá aquí.
                        </p>
                    </div>
                </div>
            </div>
        @endforelse

        {{-- Qué significa cada punto --}}
        @if (count($resumen) > 0)
            <div class="col-12">
                <div class="d-flex flex-wrap gap-3 small text-muted justify-content-center">
                    <span><span class="punto-clase asistio align-middle me-1"></span> Asistió</span>
                    <span><span class="punto-clase justificada align-middle me-1"></span> Justificada</span>
                    <span><span class="punto-clase falta align-middle me-1"></span> Falta</span>
                    <span><span class="punto-clase sin-registro align-middle me-1"></span> No te registraste</span>
                </div>
            </div>
        @endif
    </div>
@endsection
