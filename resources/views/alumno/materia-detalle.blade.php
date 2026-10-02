@extends('layouts.alumnos')
@section('title', 'Mi Progreso de Asistencia')

@section('content')
    @include('partials.estilos-progreso')

    @php
        $minimo = \App\Support\ProgresoDelAlumno::MINIMO;
        $enRiesgo = $resumen['total'] > 0 && $resumen['porcentaje'] < $minimo;

        // Cuántas clases hay de cada filtro (sólo se muestran los que tienen alguna)
        $porGrupo = $bitacora->countBy(fn($clase) => \App\Support\ProgresoDelAlumno::estado($clase['estado'])['grupo']);
        $filtros = collect([
            'asistencias' => 'Asistencias',
            'justificadas' => 'Justificadas',
            'faltas' => 'Faltas',
            'no_hubo' => 'No hubo clase',
        ])->filter(fn($texto, $grupo) => ($porGrupo[$grupo] ?? 0) > 0);
    @endphp

    <div class="row g-4">
        {{-- 1. REGRESO Y ENLACE AL HISTORIAL --}}
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="{{ route('alumno.materias', ['semestre_id' => $semestreId]) }}"
                class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> Volver a Mi Progreso
            </a>
            <a href="{{ route('alumno.historial', ['materia_id' => $materia->id, 'semestre_id' => $semestreId]) }}"
                class="btn btn-light border rounded-pill shadow-sm px-4">
                <i class="bi bi-clock-history text-marca-green me-1"></i> Ver en mi historial
            </a>
        </div>

        {{-- 2. ENCABEZADO: LA MATERIA Y SU PORCENTAJE --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="bg-marca-green" style="height: 5px;"></div>
                <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                            <h4 class="fw-bold text-dark mb-0">{{ $materia->nombre_materia }}</h4>
                            @if ($materia->clave)
                                <span class="badge bg-light text-dark border rounded-pill">{{ $materia->clave }}</span>
                            @endif
                        </div>
                        @if ($semestre)
                            <p class="text-muted small mb-3">
                                <i class="bi bi-calendar3 text-marca-green me-1"></i> Periodo {{ $semestre->nombre }}
                            </p>
                        @endif

                        {{-- Cuándo, dónde y con quién --}}
                        <div class="d-flex flex-column gap-2">
                            @foreach ($horarios as $horario)
                                <div class="small text-dark d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
                                    <span class="fw-bold">
                                        <i class="bi bi-clock text-marca-green me-1"></i>
                                        @if ($horario->tipo_reserva === 'especial' && $horario->fecha_especial)
                                            {{ ucfirst(\Carbon\Carbon::parse($horario->fecha_especial)->translatedFormat('l d/m')) }}
                                        @else
                                            {{ $horario->dia_semana }}
                                        @endif
                                        {{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }} -
                                        {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
                                    </span>
                                    @if ($horario->centroComputo)
                                        <span><i class="bi bi-pc-display text-muted me-1"></i>{{ $horario->centroComputo->nombre_centro }}</span>
                                    @endif
                                    @if ($horario->user)
                                        <span><i class="bi bi-person-video3 text-muted me-1"></i>Prof. {{ $horario->user->name }} {{ $horario->user->apellido_paterno }}</span>
                                    @endif
                                    @if ($horario->grupo)
                                        <span class="text-muted"><i class="bi bi-people me-1"></i>{{ $horario->grupo->nombre_grupo }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="anillo-progreso {{ $enRiesgo ? 'en-riesgo' : '' }} mx-auto"
                            style="--valor: {{ $resumen['porcentaje'] }}; --tamano: 120px;">
                            <span>{{ $resumen['porcentaje'] }}%</span>
                        </div>
                        @if ($resumen['total'] > 0)
                            <span class="estado-clase {{ $enRiesgo ? 'falta' : 'asistio' }} mt-2">
                                <i class="bi {{ $enRiesgo ? 'bi-shield-exclamation' : 'bi-shield-check' }}"></i>
                                {{ $enRiesgo ? 'Riesgo' : 'Regular' }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. QUÉ SIGNIFICA SU PORCENTAJE --}}
        <div class="col-12">
            @if ($resumen['total'] === 0)
                <div class="alert alert-light border rounded-4 mb-0 d-flex align-items-center gap-3">
                    <i class="bi bi-hourglass-split fs-4 text-muted"></i>
                    <div>Todavía no hay clases con asistencia en esta materia. En cuanto se tome lista, aparecerán
                        aquí.</div>
                </div>
            @elseif ($enRiesgo)
                <div class="alert rounded-4 mb-0 d-flex align-items-center gap-3 border-0 border-start border-4 border-danger"
                    style="background-color: #fff5f5;">
                    <i class="bi bi-graph-up-arrow fs-4 text-danger"></i>
                    <div>
                        <strong>Asiste a {{ $resumen['para_recuperar'] }}
                            {{ $resumen['para_recuperar'] == 1 ? 'clase' : 'clases seguidas' }}
                            para volver a {{ $minimo }}%.</strong>
                        <span class="d-block small text-muted">Si crees que hay un error en tus registros, habla con tu
                            profesor para aclararlo.</span>
                    </div>
                </div>
            @else
                <div class="alert rounded-4 mb-0 d-flex align-items-center gap-3 border-0 border-start border-4 border-marca-green"
                    style="background-color: #f0faf4;">
                    <i class="bi bi-emoji-smile fs-4 text-marca-green"></i>
                    <div><strong>¡Vas bien!</strong> Tu asistencia está arriba del {{ $minimo }}% que necesitas para
                        estar Regular.</div>
                </div>
            @endif
        </div>

        {{-- 4. LOS NÚMEROS --}}
        <div class="col-12">
            <div class="row g-2 text-center">
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-light border rounded-4 h-100">
                        <span class="d-block fw-bold text-dark fs-3">{{ $resumen['total'] }}</span>
                        <span class="text-muted fw-bold small text-uppercase">Clases</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-4 h-100">
                        <span class="d-block fw-bold text-success fs-3">{{ $resumen['asistencias'] }}</span>
                        <span class="text-success fw-bold small text-uppercase">Asistencias</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-4 h-100">
                        <span class="d-block fw-bold fs-3" style="color: #d39e00;">{{ $resumen['justificadas'] }}</span>
                        <span class="fw-bold small text-uppercase" style="color: #d39e00;">Justificadas</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div
                        class="p-3 border rounded-4 h-100 {{ $resumen['faltas'] > 0 ? 'bg-danger bg-opacity-10 border-danger border-opacity-25' : 'bg-light' }}">
                        <span class="d-block fw-bold fs-3 {{ $resumen['faltas'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $resumen['faltas'] }}</span>
                        <span class="fw-bold small text-uppercase {{ $resumen['faltas'] > 0 ? 'text-danger' : 'text-muted' }}">Faltas</span>
                    </div>
                </div>
            </div>
            @if ($resumen['sin_registro'] > 0)
                <p class="small text-muted mb-0 mt-2">
                    <i class="bi bi-info-circle text-marca-green me-1"></i>
                    Las faltas incluyen {{ $resumen['sin_registro'] }}
                    {{ $resumen['sin_registro'] == 1 ? 'clase' : 'clases' }} en las que no te registraste y tus
                    compañeros sí.
                </p>
            @endif
        </div>

        {{-- 5. CLASE POR CLASE --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div
                    class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-marca-green me-2"></i>Clase por clase
                    </h6>

                    {{-- Filtros: sólo cambian qué filas se ven --}}
                    @if ($filtros->count() > 1)
                        <div class="d-flex flex-wrap gap-1" id="filtros-clases">
                            <button type="button" class="btn btn-sm rounded-pill px-3 btn-marca-black" data-filtro="">
                                Todas ({{ $bitacora->count() }})
                            </button>
                            @foreach ($filtros as $grupo => $texto)
                                <button type="button" class="btn btn-sm rounded-pill px-3 btn-light border"
                                    data-filtro="{{ $grupo }}">
                                    {{ $texto }} ({{ $porGrupo[$grupo] }})
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($bitacora as $clase)
                        @php
                            $estado = \App\Support\ProgresoDelAlumno::estado($clase['estado']);
                            $registro = $clase['asistencia'];
                            $fecha = \Carbon\Carbon::parse($clase['fecha']);
                        @endphp
                        <div class="list-group-item px-4 py-3 d-flex gap-3 align-items-start fila-clase"
                            data-grupo="{{ $estado['grupo'] }}">
                            {{-- Fecha --}}
                            <div class="text-center flex-shrink-0 bg-light border rounded-3 py-1" style="width: 58px;">
                                <span class="d-block text-muted fw-bold text-uppercase"
                                    style="font-size: 0.65rem;">{{ rtrim($fecha->translatedFormat('D'), '.') }}</span>
                                <span class="d-block fw-bold text-dark lh-1">{{ $fecha->format('d') }}</span>
                                <span class="d-block text-muted" style="font-size: 0.7rem;">{{ rtrim($fecha->translatedFormat('M'), '.') }}</span>
                            </div>

                            {{-- Qué pasó --}}
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <span class="estado-clase {{ $estado['clase'] }}">
                                        <i class="bi {{ $estado['icono'] }}"></i> {{ $estado['texto'] }}
                                    </span>
                                    @if ($clase['horario'])
                                        <span class="small text-muted">
                                            <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($clase['horario']->hora_inicio)->format('H:i') }}
                                            - {{ \Carbon\Carbon::parse($clase['horario']->hora_fin)->format('H:i') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="small text-muted mt-2">
                                    @switch($clase['estado'])
                                        @case('presente')
                                            Registrada a las
                                            {{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('h:i A') }}
                                            ·
                                            @if ($registro->equipo_personal)
                                                <i class="bi bi-laptop"></i> Equipo personal
                                            @elseif ($registro->numero_maquina)
                                                <i class="bi bi-pc-display"></i> PC #{{ $registro->numero_maquina }}
                                            @else
                                                Sin PC anotada
                                            @endif
                                        @break

                                        @case('justificado')
                                            Tu profesor justificó esta falta. Cuenta como asistencia.
                                        @break

                                        @case('falta')
                                            Tu profesor te marcó falta en la lista.
                                        @break

                                        @case('sin_registro')
                                            No te registraste y tus compañeros sí. Cuenta como falta.
                                        @break

                                        @case('no_impartida')
                                            El profesor no asistió. No cuenta para tu porcentaje.
                                        @break

                                        @case('no_impartida_justificada')
                                            El profesor justificó su ausencia. No cuenta para tu porcentaje.
                                        @break
                                    @endswitch
                                </div>

                                {{-- La nota del profesor, a la vista y tal como la escribió --}}
                                @if ($registro && $registro->comentario)
                                    <div class="small text-dark bg-light border-start border-3 border-marca-green rounded-end px-3 py-2 mt-2"
                                        style="white-space: pre-line;"><i class="bi bi-chat-left-text text-marca-green me-1"></i><strong>Nota del profesor:</strong> {{ $registro->comentario }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 px-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light border rounded-circle mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="bi bi-calendar3 fs-2 text-muted opacity-50"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Aún no hay clases registradas</h6>
                            <p class="text-muted small mb-0">Aquí verás cada clase de esta materia en cuanto se tome
                                asistencia.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Filtros de "Clase por clase": sólo muestran u ocultan filas
        document.querySelectorAll('#filtros-clases [data-filtro]').forEach(function(boton) {
            boton.addEventListener('click', function() {
                const filtro = this.dataset.filtro;

                document.querySelectorAll('#filtros-clases [data-filtro]').forEach(function(otro) {
                    otro.classList.toggle('btn-marca-black', otro === boton);
                    otro.classList.toggle('btn-light', otro !== boton);
                    otro.classList.toggle('border', otro !== boton);
                });

                document.querySelectorAll('.fila-clase').forEach(function(fila) {
                    fila.classList.toggle('d-none', filtro !== '' && fila.dataset.grupo !== filtro);
                });
            });
        });
    </script>
@endpush
