@extends('layouts.profesor')
@section('title', 'Historial de Clases')

@section('content')
    @include('partials.estilos-progreso')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .fila-clase {
            scroll-margin-top: 110px;
            transition: background-color .3s ease;
        }

        /* Al volver de un pase de lista, la clase de la que viene se resalta */
        .fila-clase:target {
            background-color: #fffbe0;
            box-shadow: inset 4px 0 0 var(--marca-yellow);
        }

        .encabezado-semana {
            font-size: .75rem;
            letter-spacing: .5px;
        }
    </style>

    @php
        $filtros = [
            'sin_lista' => 'Sin lista',
            'parcial' => 'Incompletas',
            'completa' => 'Completas',
            'no_impartida' => 'No impartidas',
        ];
    @endphp

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black">
                    <i class="bi bi-clock-history text-marca-green me-2"></i> Historial de Clases
                </h3>
                <p class="text-muted small mb-0 mt-1">Tus clases ya dadas y cómo quedó la lista de cada una.</p>
            </div>

            {{-- Periodo (si escogió un día, manda el semestre de ese día) --}}
            @if (! $dia)
                <form action="{{ route('profesor.historial') }}" method="GET"
                    class="d-flex align-items-center bg-light p-2 rounded-pill border shadow-sm">
                    <i class="bi bi-mortarboard-fill text-marca-green ms-2 me-2"></i>
                    <select name="semestre_id" class="form-select form-select-sm bg-transparent border-0 fw-bold shadow-none pe-4"
                        style="min-width: 200px; cursor: pointer;" onchange="this.form.submit()" aria-label="Periodo">
                        @foreach ($semestres as $sem)
                            <option value="{{ $sem->id }}" {{ $semestre && $semestre->id == $sem->id ? 'selected' : '' }}>
                                {{ $sem->nombre }} {{ $sem->es_activo ? '(Actual)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- AVISOS (p. ej. al abrir la lista de una fecha que no corresponde) --}}
        @if (session('error') || $fechaInvalida)
            <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm border-0 border-start border-4 border-warning"
                role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ session('error') ?? 'La fecha que escribiste no es válida; se muestran todas tus clases.' }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        {{-- FILTROS --}}
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">

                    {{-- Estado de la lista (filtra al momento, sin recargar) --}}
                    <div class="col-lg-7">
                        <label class="form-label fw-bold text-muted small text-uppercase mb-2">Estado de la lista</label>
                        <div class="d-flex flex-wrap gap-1" id="filtros-estado">
                            <button type="button" class="btn btn-sm rounded-pill px-3 btn-marca-black" data-estado="">
                                Todas ({{ $clases->count() }})
                            </button>
                            @foreach ($filtros as $estado => $texto)
                                @if (($conteos[$estado] ?? 0) > 0)
                                    <button type="button" class="btn btn-sm rounded-pill px-3 btn-light border"
                                        data-estado="{{ $estado }}">
                                        @if ($estado === 'sin_lista')
                                            <i class="bi bi-exclamation-circle-fill text-danger me-1"></i>
                                        @endif
                                        {{ $texto }} ({{ $conteos[$estado] }})
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Clase --}}
                    <div class="col-sm-6 col-lg-3">
                        <label for="filtro-clase" class="form-label fw-bold text-muted small text-uppercase mb-2">Clase</label>
                        <select id="filtro-clase" class="form-select form-select-sm shadow-sm">
                            <option value="">Todas mis clases</option>
                            @foreach ($opcionesClase as $opcion)
                                <option value="{{ $opcion['clave'] }}">{{ $opcion['texto'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Ir directo a un día --}}
                    <div class="col-sm-6 col-lg-2">
                        <form action="{{ route('profesor.historial') }}" method="GET">
                            <label for="fecha" class="form-label fw-bold text-muted small text-uppercase mb-2">Ir a un día</label>
                            <input type="date" id="fecha" name="fecha" class="form-control form-control-sm shadow-sm"
                                value="{{ $dia }}" max="{{ date('Y-m-d') }}" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>

                @if ($dia)
                    <div class="mt-3 small">
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                            <i class="bi bi-calendar-event text-marca-green me-1"></i>
                            Sólo el {{ \Carbon\Carbon::parse($dia)->translatedFormat('l d \d\e F \d\e Y') }}
                        </span>
                        <a href="{{ route('profesor.historial') }}" class="ms-2 fw-bold text-marca-green text-decoration-none">
                            Ver todas mis clases <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- LAS CLASES, POR SEMANA --}}
        @if ($clases->isEmpty())
            <div class="card shadow-sm border-0 rounded-4 bg-light">
                <div class="card-body text-center py-5">
                    <div class="bg-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm"
                        style="width: 80px; height: 80px;">
                        <i class="bi bi-calendar-x fs-1 text-warning"></i>
                    </div>
                    @if ($dia && ! $semestre)
                        <h4 class="fw-bold text-dark mb-2">Fecha fuera de todo periodo escolar</h4>
                        <p class="text-muted mb-0">Esa fecha no pertenece a ningún semestre registrado, así que no hay
                            clases que consultar.</p>
                    @elseif ($dia)
                        <h4 class="fw-bold text-dark mb-2">Sin clases ese día</h4>
                        <p class="text-muted mb-0">No tenías clases programadas el
                            <strong>{{ \Carbon\Carbon::parse($dia)->translatedFormat('l d \d\e F') }}</strong>.</p>
                    @elseif (! $semestre)
                        <h4 class="fw-bold text-dark mb-2">No hay un periodo activo</h4>
                        <p class="text-muted mb-0">Pide al administrador que active el semestre correspondiente.</p>
                    @else
                        <h4 class="fw-bold text-dark mb-2">Aún no hay clases pasadas</h4>
                        <p class="text-muted mb-0">En cuanto des tu primera clase del periodo
                            <strong>{{ $semestre->nombre }}</strong>, aparecerá aquí.</p>
                    @endif
                </div>
            </div>
        @else
            <div id="lista-clases">
                @foreach ($porSemana as $inicioSemana => $clasesSemana)
                    @php $lunes = \Carbon\Carbon::parse($inicioSemana); @endphp
                    <div class="grupo-semana mb-4">
                        <div class="encabezado-semana text-uppercase fw-bold text-muted mb-2 ps-1">
                            <i class="bi bi-calendar-week text-marca-green me-1"></i>
                            Semana del {{ $lunes->translatedFormat('d \d\e F') }} al
                            {{ $lunes->copy()->addDays(6)->translatedFormat('d \d\e F') }}
                        </div>

                        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                            <div class="list-group list-group-flush">
                                @foreach ($clasesSemana as $clase)
                                    @php
                                        $horario = $clase['horario'];
                                        $estado = \App\Support\ClasesDelProfesor::ESTADOS[$clase['estado']];
                                        $fecha = \Carbon\Carbon::parse($clase['fecha']);
                                        $urlLista = route('profesor.revisar-clase', ['horario_id' => $horario->id, 'fecha' => $clase['fecha']]);
                                    @endphp
                                    <div class="list-group-item px-3 px-md-4 py-3 fila-clase"
                                        id="clase-{{ $horario->id }}-{{ $clase['fecha'] }}"
                                        data-estado="{{ $clase['estado'] }}"
                                        data-clase="{{ $horario->materia_id }}-{{ $horario->grupo_id }}">
                                        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">

                                            <div class="d-flex gap-3 align-items-center flex-grow-1" style="min-width: 0;">
                                                {{-- Fecha --}}
                                                <div class="text-center flex-shrink-0 bg-light border rounded-3 py-1"
                                                    style="width: 58px;">
                                                    <span class="d-block text-muted fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">{{ rtrim($fecha->translatedFormat('D'), '.') }}</span>
                                                    <span class="d-block fw-bold text-dark lh-1">{{ $fecha->format('d') }}</span>
                                                    <span class="d-block text-muted"
                                                        style="font-size: 0.7rem;">{{ rtrim($fecha->translatedFormat('M'), '.') }}</span>
                                                </div>

                                                {{-- La clase --}}
                                                <div style="min-width: 0;">
                                                    <div class="fw-bold text-marca-black">
                                                        {{ $horario->materia->nombre_materia ?? 'Clase' }}</div>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-people me-1"></i>{{ $horario->grupo->nombre_grupo ?? 'Sin grupo' }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }}
                                                        - {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
                                                        @if ($horario->centroComputo)
                                                            · <i class="bi bi-pc-display me-1"></i>{{ $horario->centroComputo->nombre_centro }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Cómo quedó la lista --}}
                                            <div class="text-md-end flex-shrink-0" style="min-width: 210px;">
                                                <span class="estado-clase {{ $estado['clase'] }}">
                                                    <i class="bi {{ $estado['icono'] }}"></i> {{ $estado['texto'] }}
                                                </span>
                                                <div class="small text-muted mt-1">
                                                    @switch($clase['estado'])
                                                        @case('sin_lista')
                                                            Nadie quedó registrado
                                                        @break

                                                        @case('parcial')
                                                            {{ $clase['registrados'] }} de {{ $clase['alumnos'] }} alumnos registrados
                                                        @break

                                                        @case('completa')
                                                            {{ $clase['presentes'] }} {{ $clase['presentes'] == 1 ? 'presente' : 'presentes' }}
                                                            · {{ $clase['faltas'] }} {{ $clase['faltas'] == 1 ? 'falta' : 'faltas' }}
                                                            @if ($clase['justificadas'])
                                                                · {{ $clase['justificadas'] }} {{ $clase['justificadas'] == 1 ? 'justificada' : 'justificadas' }}
                                                            @endif
                                                        @break

                                                        @case('no_impartida')
                                                            {{ $clase['profesor'] === 'justificado' ? 'Registrada como falta justificada' : 'Registrada como falta' }}
                                                            tuya
                                                        @break
                                                    @endswitch
                                                </div>
                                            </div>

                                            {{-- Acción (mismo ancho en todas, para que se alineen) --}}
                                            <div class="flex-shrink-0" style="min-width: 170px;">
                                                @if ($clase['estado'] === 'sin_lista')
                                                    <a href="{{ $urlLista }}"
                                                        class="btn btn-marca-green btn-sm rounded-pill px-3 fw-bold w-100">
                                                        <i class="bi bi-card-checklist me-1"></i> Pasar lista
                                                    </a>
                                                @elseif ($clase['estado'] === 'parcial')
                                                    <a href="{{ $urlLista }}"
                                                        class="btn btn-outline-marca-green btn-sm rounded-pill px-3 fw-bold w-100">
                                                        <i class="bi bi-pencil-square me-1"></i> Completar lista
                                                    </a>
                                                @else
                                                    <a href="{{ $urlLista }}"
                                                        class="btn btn-light border btn-sm rounded-pill px-3 fw-bold w-100">
                                                        <i class="bi bi-eye me-1"></i> Ver / editar
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Cuando los filtros no dejan ninguna --}}
                <div id="sin-resultados" class="text-center text-muted py-5 d-none">
                    <i class="bi bi-funnel fs-1 d-block mb-2 opacity-50"></i>
                    Ninguna clase coincide con los filtros.
                </div>
            </div>
        @endif

    </div>

@endsection

@push('scripts')
    <script>
        // Filtros del historial: sólo muestran u ocultan filas (y las semanas que quedan vacías)
        (function() {
            const botones = document.querySelectorAll('#filtros-estado [data-estado]');
            const selectClase = document.getElementById('filtro-clase');
            if (!botones.length) return;

            let estado = '';

            function aplicar() {
                const clase = selectClase ? selectClase.value : '';
                let visibles = 0;

                document.querySelectorAll('.grupo-semana').forEach(function(semana) {
                    let enSemana = 0;
                    semana.querySelectorAll('.fila-clase').forEach(function(fila) {
                        const ver = (!estado || fila.dataset.estado === estado) && (!clase || fila.dataset.clase === clase);
                        fila.classList.toggle('d-none', !ver);
                        if (ver) enSemana++;
                    });
                    semana.classList.toggle('d-none', enSemana === 0);
                    visibles += enSemana;
                });

                const vacio = document.getElementById('sin-resultados');
                if (vacio) vacio.classList.toggle('d-none', visibles > 0);
            }

            function elegir(valor) {
                estado = valor;
                botones.forEach(function(b) {
                    const activo = b.dataset.estado === valor;
                    b.classList.toggle('btn-marca-black', activo);
                    b.classList.toggle('btn-light', !activo);
                    b.classList.toggle('border', !activo);
                });
                aplicar();
            }

            botones.forEach(function(b) {
                b.addEventListener('click', function() { elegir(this.dataset.estado); });
            });
            if (selectClase) selectClase.addEventListener('change', aplicar);

            // Desde el aviso de "Clases de Hoy" llega con ?estado=sin_lista
            const pedido = new URLSearchParams(window.location.search).get('estado');
            if (pedido && document.querySelector('#filtros-estado [data-estado="' + pedido + '"]')) {
                elegir(pedido);
            }
        })();
    </script>
@endpush
