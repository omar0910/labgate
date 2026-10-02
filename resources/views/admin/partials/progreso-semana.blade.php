{{--
    Progreso de las clases de la semana. Lo usan el panel de inicio y la vista
    "Semana en curso"; los números salen de App\Support\AgendaSemanal.

    Recibe: $semana (la agenda) y, opcional, $conAcciones (botones a la derecha).
    También la usa el inicio del encargado: sus botones llevan a su propia vista.
--}}
@php
    $conAcciones = $conAcciones ?? true;
    $rutaSemana = Auth::user()->rol === 'Administrador' ? 'admin.semana' : 'encargado.semana';
    $c = $semana['conteo'];
    $total = max(1, $semana['total']);

    // Tramos de la barra, en el orden en que se leen
    $tramos = [
        'asistio' => ['Impartidas', 'var(--marca-green)', ''],
        'justificado' => ['Justificadas', 'var(--marca-yellow)', ''],
        'falta' => ['Faltas', '#dc3545', ''],
        'por_registrar' => ['Por registrar', '#6c757d', ''],
        'en_curso' => ['En curso', 'var(--marca-green)', 'progress-bar-striped progress-bar-animated opacity-50'],
    ];

    // "21 al 27 de septiembre", o "28 de septiembre al 4 de octubre" si cambia de mes
    $rango = $semana['lunes']->month === $semana['domingo']->month
        ? $semana['lunes']->format('d') . ' al ' . $semana['domingo']->translatedFormat('d \d\e F')
        : $semana['lunes']->translatedFormat('d \d\e F') . ' al ' . $semana['domingo']->translatedFormat('d \d\e F');
    $esEstaSemana = $semana['lunes']->isSameDay(now()->startOfWeek());
@endphp

<div class="card shadow-sm border-0 bg-white rounded-4 overflow-hidden progreso-semana">
    <div class="card-body p-4">
        <div class="row g-4 align-items-center">

            {{-- Título --}}
            <div class="{{ $conAcciones ? 'col-lg-3' : 'col-lg-4' }}">
                <div class="d-flex align-items-center">
                    <div class="bg-light p-3 rounded-circle me-3 text-marca-green border border-marca-green border-opacity-25 shadow-sm">
                        <i class="bi bi-graph-up-arrow fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-marca-black">Rendimiento Semanal</h5>
                        <small class="text-muted d-block">{{ $rango }}</small>
                        @if (!$semana['enPeriodo'])
                            <span class="badge bg-light text-muted border mt-1">Fuera del periodo de clases</span>
                        @elseif ($esEstaSemana)
                            <span class="badge bg-marca-yellow text-marca-black mt-1">Semana en curso</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Barra y cifras --}}
            <div class="{{ $conAcciones ? 'col-lg-6' : 'col-lg-8' }}">
                @if ($semana['total'] === 0)
                    <p class="text-muted small mb-0">
                        <i class="bi bi-calendar-x me-1"></i>
                        {{ $semana['enPeriodo'] ? 'No hay clases programadas esta semana.' : 'Esta semana no pertenece al semestre activo.' }}
                    </p>
                @else
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="fw-bold text-muted text-uppercase small">Progreso de Clases</span>
                        <span class="text-marca-black fw-bold">
                            <span class="fs-5">{{ $c['asistio'] }}</span>
                            <span class="text-muted">/ {{ $semana['total'] }} impartidas</span>
                        </span>
                    </div>

                    {{-- Cada tramo es una parte de las clases de la semana; lo gris claro es lo que aún no toca --}}
                    <div class="progress-stacked mb-2" style="height: 14px; border-radius: 10px; background-color: #e9ecef;">
                        @foreach ($tramos as $estado => [$nombre, $color, $extra])
                            @if ($c[$estado] > 0)
                                <div class="progress" role="progressbar" aria-label="{{ $nombre }}"
                                    aria-valuenow="{{ $c[$estado] }}" aria-valuemin="0" aria-valuemax="{{ $semana['total'] }}"
                                    style="width: {{ ($c[$estado] / $total) * 100 }}%; height: 14px;" title="{{ $nombre }}: {{ $c[$estado] }}">
                                    <div class="progress-bar {{ $extra }}" style="background-color: {{ $color }};"></div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Leyenda --}}
                    <div class="d-flex flex-wrap gap-3 small">
                        @foreach ($tramos as $estado => [$nombre, $color, $extra])
                            @if ($c[$estado] > 0 || $estado === 'asistio')
                                <span class="text-muted">
                                    <span class="d-inline-block rounded-circle me-1 {{ $estado === 'en_curso' ? 'opacity-50' : '' }}"
                                        style="width: 9px; height: 9px; background-color: {{ $color }};"></span>
                                    {{ $nombre }} <strong class="text-marca-black">{{ $c[$estado] }}</strong>
                                </span>
                            @endif
                        @endforeach
                        @if ($c['proxima'] > 0)
                            <span class="text-muted">
                                <span class="d-inline-block rounded-circle me-1 border"
                                    style="width: 9px; height: 9px; background-color: #e9ecef;"></span>
                                Próximas <strong class="text-marca-black">{{ $c['proxima'] }}</strong>
                            </span>
                        @endif
                    </div>

                    <small class="text-muted mt-2 d-block">
                        <i class="bi bi-info-circle text-marca-green me-1"></i>
                        @if (is_null($semana['cumplimiento']))
                            Aún no hay clases que debieran haberse dado esta semana.
                        @else
                            Cumplimiento a hoy:
                            <strong class="text-marca-black">{{ $semana['cumplimiento'] }}%</strong>
                            ({{ $c['asistio'] + $c['justificado'] }} de {{ $semana['yaDebieron'] }}
                            {{ $semana['yaDebieron'] == 1 ? 'clase que ya debió darse' : 'clases que ya debieron darse' }}).
                        @endif
                    </small>
                @endif
            </div>

            {{-- Acciones --}}
            @if ($conAcciones)
                <div class="col-lg-3 text-center text-lg-end">
                    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
                        <a href="{{ route($rutaSemana) }}" class="btn btn-marca-black rounded-pill shadow-sm px-4">
                            <i class="bi bi-eye me-1"></i> Ver Detalle Completo
                        </a>
                        @if ($c['por_registrar'] > 0)
                            {{-- Las que ya pasaron sin el registro del profesor --}}
                            <a href="{{ route($rutaSemana, ['estado' => 'por_registrar']) }}"
                                class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                                <i class="bi bi-hourglass-split me-1"></i>
                                {{ $c['por_registrar'] }} por registrar
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
