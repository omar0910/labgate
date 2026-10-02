@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')
@section('title', 'Semana de Clases')

@section('content')

    @php
        $estados = \App\Support\AgendaSemanal::ESTADOS;

        // La ven el admin y el encargado: cada botón lleva a la pantalla de su rol
        $esAdmin = Auth::user()->rol === 'Administrador';
        $rutas = $esAdmin
            ? ['semana' => 'admin.semana', 'panel' => 'admin.dashboard', 'dia' => 'admin.reportes.clases-hoy', 'lista' => 'admin.ver-asistencia']
            : ['semana' => 'encargado.semana', 'panel' => 'encargado.inicio', 'dia' => 'encargado.dashboard', 'lista' => 'encargado.ver-asistencia'];

        $lunes = $semana['lunes'];
        $esEstaSemana = $lunes->isSameDay(now()->startOfWeek());
        $hoy = now()->format('Y-m-d');

        // Para moverse de semana conservando el laboratorio y el estado elegidos
        $irA = fn($dia) => route($rutas['semana'], array_filter([
            'semana' => $dia->format('Y-m-d'),
            'centro_id' => $centroSeleccionadoId !== 'todos' ? $centroSeleccionadoId : null,
            'estado' => $estadoFiltro,
        ]));
        $conEstado = fn($estado) => route($rutas['semana'], array_filter([
            'semana' => $esEstaSemana ? null : $lunes->format('Y-m-d'),
            'centro_id' => $centroSeleccionadoId !== 'todos' ? $centroSeleccionadoId : null,
            'estado' => $estado,
        ]));

        $clases = $estadoFiltro ? $semana['clases']->where('estado', $estadoFiltro) : $semana['clases'];

        $insignias = [
            'asistio' => ['bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'bi-check-circle'],
            'justificado' => ['bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25', 'bi-file-earmark-text'],
            'falta' => ['bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', 'bi-x-circle'],
            'por_registrar' => ['bg-marca-yellow text-marca-black', 'bi-hourglass-split'],
            'en_curso' => ['bg-marca-green text-white', 'bi-broadcast'],
            'proxima' => ['bg-light text-muted border', 'bi-clock'],
        ];
    @endphp

    <div class="container-fluid py-4">

        {{-- ENCABEZADO (misma estructura que Clases de Hoy) --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <h4 class="fw-bold text-marca-black mb-1"><i class="bi bi-calendar-week text-marca-green me-2"></i>
                            Semana de Clases</h4>
                        <p class="text-muted small mb-0 mt-1">
                            Mostrando la semana:
                            <span
                                class="badge {{ $esEstaSemana ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-dark' }} border fw-bold fs-6 ms-1">
                                {{ $lunes->format('d/m') }} al {{ $semana['domingo']->format('d/m/Y') }}
                                @if ($esEstaSemana)
                                    <i class="bi bi-calendar-check ms-1"></i> (Esta semana)
                                @endif
                            </span>
                        </p>
                    </div>
                    <div class="col-md-7 mt-3 mt-md-0">

                        <div class="d-flex justify-content-md-end gap-2 mb-3">
                            <a href="{{ route('admin.bitacora.pendientes', ['origen' => 'semana', 'semana' => $lunes->format('Y-m-d'), 'centro_id' => $centroSeleccionadoId]) }}"
                                class="btn bg-marca-green text-white rounded-pill shadow-sm px-4 fw-bold"
                                title="Clases pasadas sin la asistencia del profesor">
                                <i class="bi bi-clipboard-check me-1"></i> Clases pendientes
                            </a>
                            <a href="{{ route($rutas['panel']) }}"
                                class="btn btn-outline-secondary rounded-pill shadow-sm px-4" title="Volver al Inicio">
                                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
                            </a>
                        </div>

                        <form action="{{ route($rutas['semana']) }}" method="GET"
                            class="row g-2 justify-content-md-end align-items-end">
                            @if ($estadoFiltro)
                                <input type="hidden" name="estado" value="{{ $estadoFiltro }}">
                            @endif

                            <div class="col-auto">
                                <label class="small fw-bold text-muted d-block mb-1">Laboratorio</label>
                                <select name="centro_id"
                                    class="form-select border border-secondary border-opacity-25 bg-light rounded-3 shadow-sm"
                                    style="min-width: 220px;" onchange="this.form.submit()">
                                    <option value="todos" {{ $centroSeleccionadoId == 'todos' ? 'selected' : '' }}>Todos
                                        los Laboratorios</option>
                                    @foreach ($centros as $centro)
                                        <option value="{{ $centro->id }}"
                                            {{ $centro->id == $centroSeleccionadoId ? 'selected' : '' }}>
                                            {{ $centro->nombre_centro }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-auto">
                                <label class="small fw-bold text-muted d-block mb-1">Semana</label>
                                <div class="input-group shadow-sm rounded-3">
                                    <a href="{{ $irA($lunes->copy()->subWeek()) }}"
                                        class="btn btn-light border border-secondary border-opacity-25" title="Semana anterior">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                    {{-- Cualquier día elegido muestra su semana completa --}}
                                    <input type="date" name="semana" value="{{ $lunes->format('Y-m-d') }}"
                                        class="form-control border border-secondary border-opacity-25 bg-light"
                                        onchange="this.form.submit()">
                                    <a href="{{ $irA($lunes->copy()->addWeek()) }}"
                                        class="btn btn-light border border-secondary border-opacity-25" title="Semana siguiente">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                    @if (!$esEstaSemana)
                                        <a href="{{ $irA(now()) }}" class="btn bg-marca-green text-white fw-bold px-3"
                                            title="Regresar a esta semana">Hoy</a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- PROGRESO DE LA SEMANA --}}
        <div class="mb-4">
            @include('admin.partials.progreso-semana', ['semana' => $semana, 'conAcciones' => false])
        </div>

        @if ($semana['total'] > 0)
            {{-- Filtro por estado --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="small fw-bold text-muted text-uppercase me-1">Mostrar:</span>
                <a href="{{ $conEstado(null) }}"
                    class="btn btn-sm rounded-pill px-3 fw-bold {{ !$estadoFiltro ? 'btn-marca-black' : 'btn-outline-secondary' }}">
                    Todas <span class="opacity-75">({{ $semana['total'] }})</span>
                </a>
                @foreach ($estados as $estado => $nombre)
                    @if ($semana['conteo'][$estado] > 0 || $estadoFiltro === $estado)
                        <a href="{{ $conEstado($estado) }}"
                            class="btn btn-sm rounded-pill px-3 fw-bold {{ $estadoFiltro === $estado ? 'btn-marca-black' : 'btn-outline-secondary' }}">
                            {{ $nombre }} <span class="opacity-75">({{ $semana['conteo'][$estado] }})</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Clases de la semana que no cuentan porque su horario se registró después --}}
        @if ($semana['anteriores'] > 0 && !$estadoFiltro)
            <div class="alert bg-light border rounded-4 small text-muted d-flex align-items-start gap-2 mb-4">
                <i class="bi bi-info-circle text-marca-green fs-5"></i>
                <div>
                    <strong class="text-marca-black">{{ $semana['anteriores'] }}
                        {{ $semana['anteriores'] == 1 ? 'clase' : 'clases' }} de esta semana no
                        {{ $semana['anteriores'] == 1 ? 'aparece' : 'aparecen' }}</strong>
                    porque {{ $semana['anteriores'] == 1 ? 'su horario se registró' : 'sus horarios se registraron' }}
                    en el sistema después de esa fecha. Si tienes las hojas de asistencia, puedes capturarlas en
                    <a href="{{ route('admin.bitacora.pendientes', ['origen' => 'semana', 'semana' => $lunes->format('Y-m-d'), 'centro_id' => $centroSeleccionadoId]) }}" class="text-marca-green fw-bold">Clases pendientes</a>.
                </div>
            </div>
        @endif

        @if (!$semana['enPeriodo'])
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-4 shadow-sm"
                        style="width: 100px; height: 100px;">
                        <i class="bi bi-calendar-x text-marca-yellow fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-marca-black">Fuera del periodo de clases</h4>
                    <p class="text-muted mx-auto mb-0" style="max-width: 460px;">
                        @if ($semestreActivo)
                            El semestre activo es <strong class="text-marca-green">{{ $semestreActivo->nombre }}</strong>
                            ({{ \Carbon\Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') }} al
                            {{ \Carbon\Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') }}).
                        @else
                            No hay ningún semestre marcado como activo.
                        @endif
                    </p>
                </div>
            </div>
        @else
            {{-- DÍA POR DÍA --}}
            @php $mostroAlgo = false; @endphp
            @for ($d = 0; $d < 7; $d++)
                @php
                    $dia = $lunes->copy()->addDays($d);
                    $fecha = $dia->format('Y-m-d');
                    $delDia = $clases->where('fecha', $fecha)->values();
                    $motivoInhabil = $semana['inhabiles'][$fecha] ?? null;
                    // Los días sin nada que mostrar se omiten (sábados y domingos, casi siempre)
                    $mostrar = $delDia->isNotEmpty() || ($motivoInhabil && !$estadoFiltro);
                @endphp

                @if ($mostrar)
                    @php $mostroAlgo = true; @endphp
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 {{ $fecha === $hoy ? 'border-start border-5 border-marca-yellow' : '' }}">
                        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 px-4">
                            <h6 class="fw-bold text-marca-black mb-0">
                                <i class="bi bi-calendar-event text-marca-green me-2"></i>
                                {{ ucfirst($dia->translatedFormat('l d \d\e F')) }}
                                @if ($fecha === $hoy)
                                    <span class="badge bg-marca-yellow text-marca-black ms-2">Hoy</span>
                                @endif
                                @if ($motivoInhabil)
                                    <span class="badge bg-dark ms-2"><i class="bi bi-star-fill text-warning me-1"></i> Día
                                        inhábil: {{ $motivoInhabil }}</span>
                                @endif
                            </h6>
                            @if ($delDia->isNotEmpty())
                                <div class="d-flex align-items-center gap-3">
                                    <span class="small text-muted">{{ $delDia->count() }}
                                        {{ $delDia->count() == 1 ? 'clase' : 'clases' }}</span>
                                    {{-- En "Clases de hoy" se registra la asistencia del profesor de ese día --}}
                                    <a href="{{ route($rutas['dia'], ['fecha' => $fecha, 'centro_id' => $centroSeleccionadoId, 'origen' => 'semana']) }}"
                                        class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                                        Abrir el día <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if ($delDia->isEmpty())
                            <div class="card-body text-muted small px-4 py-3">
                                Las clases de este día no cuentan en el progreso.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4 border-0">Hora</th>
                                            <th class="border-0">Materia / Grupo</th>
                                            <th class="border-0">Profesor</th>
                                            <th class="border-0">Laboratorio</th>
                                            <th class="text-center border-0" title="Alumnos que registraron asistencia">Alumnos</th>
                                            <th class="text-center border-0">Estado</th>
                                            <th class="text-center pe-4 border-0">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($delDia as $clase)
                                            @php
                                                $h = $clase['horario'];
                                                [$estilo, $icono] = $insignias[$clase['estado']];
                                            @endphp
                                            <tr>
                                                <td class="ps-4 text-nowrap fw-bold text-marca-green">
                                                    {{ \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') }} -
                                                    {{ \Carbon\Carbon::parse($h->hora_fin)->format('H:i') }}
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark">{{ $h->materia->nombre_materia ?? 'N/A' }}</div>
                                                    <small class="text-muted">{{ $h->grupo->nombre_grupo ?? 'N/A' }}</small>
                                                </td>
                                                <td class="small fw-semibold text-dark">
                                                    {{ trim(($h->user->name ?? '') . ' ' . ($h->user->apellido_paterno ?? '')) ?: 'Sin asignar' }}
                                                </td>
                                                <td class="small fw-bold text-marca-green">{{ $h->centroComputo->nombre_centro ?? 'N/A' }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border rounded-pill px-3">
                                                        {{ $clase['alumnos'] }} <i class="bi bi-people-fill ms-1 text-muted"></i>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge rounded-pill px-3 py-2 {{ $estilo }}">
                                                        <i class="bi {{ $icono }} me-1"></i>{{ $estados[$clase['estado']] }}
                                                    </span>
                                                </td>
                                                <td class="text-center pe-4">
                                                    @if (in_array($clase['estado'], ['por_registrar', 'en_curso']))
                                                        <a href="{{ route($rutas['dia'], ['fecha' => $fecha, 'centro_id' => $centroSeleccionadoId, 'origen' => 'semana']) }}"
                                                            class="btn btn-sm bg-marca-yellow text-marca-black rounded-pill fw-bold px-3 text-nowrap shadow-sm">
                                                            <i class="bi bi-pencil-square me-1"></i> Registrar
                                                        </a>
                                                    @elseif ($clase['estado'] === 'proxima')
                                                        <span class="text-muted small">—</span>
                                                    @else
                                                        <a href="{{ route($rutas['lista'], ['id' => $h->id, 'fecha' => $fecha, 'centro_id' => $centroSeleccionadoId, 'origen' => 'semana']) }}"
                                                            class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-3 text-nowrap">
                                                            <i class="bi bi-people-fill me-1"></i> Lista
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            @endfor

            @if (!$mostroAlgo)
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-4 shadow-sm"
                            style="width: 100px; height: 100px;">
                            <i class="bi bi-calendar-check text-marca-green fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-marca-black">
                            {{ $estadoFiltro ? 'Nada en este estado' : 'Semana sin clases' }}
                        </h4>
                        <p class="text-muted mx-auto mb-0" style="max-width: 420px;">
                            {{ $estadoFiltro
                                ? 'No hay clases "' . $estados[$estadoFiltro] . '" en esta semana con el laboratorio seleccionado.'
                                : 'No hay clases programadas esta semana en los laboratorios seleccionados.' }}
                        </p>
                    </div>
                </div>
            @endif
        @endif
    </div>

@endsection
