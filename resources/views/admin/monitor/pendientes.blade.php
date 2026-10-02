@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Clases pendientes')

@php
    // Se abre desde la Bitácora, la Semana, Clases de hoy o el Rendimiento por
    // Asignatura: "Volver" regresa ahí y el menú marca esa sección. Sin origen,
    // a la Bitácora, como siempre.
    $regreso = \App\Support\Origen::de(request()) ?? [
        'url'   => route('admin.bitacora.index'),
        'texto' => 'Volver a la Bitácora',
        'menu'  => 'monitor',
    ];
    $conOrigen = \App\Support\Origen::parametros(request());
@endphp

@section('menu', $regreso['menu'])

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
@endpush

@section('content')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f1f8f5;
            transition: all .2s ease;
        }

        .form-check-input:checked {
            background-color: #009B4D;
            border-color: #009B4D;
        }

        .fila-marcada {
            background-color: rgba(0, 155, 77, .07) !important;
        }

        .barra-lote {
            position: sticky;
            top: .5rem;
            z-index: 5;
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-clipboard-check text-marca-green me-2"></i>
                    Captura desde las hojas de lista</h3>
                <p class="text-muted small mb-0 mt-1">
                    Clases ya pasadas que no tienen la asistencia del profesor. Captúralas de las hojas de lista.
                    @if ($semestre)
                        Semestre: <span class="fw-bold text-marca-green">{{ $semestre->nombre }}</span>
                    @endif
                </p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ $regreso['url'] }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> {{ $regreso['texto'] }}
                </a>
            </div>
        </div>

        {{-- MENSAJES --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger shadow-sm rounded-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errors->first() }}
            </div>
        @endif

        @if (!$semestre)
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted opacity-25 d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">No hay un semestre activo</h5>
                    <p class="text-muted small mb-0">Activa un semestre para ver sus clases pendientes.</p>
                </div>
            </div>
        @else
            {{-- FILTROS --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
                <div class="card-body p-4">
                    <form action="{{ route('admin.bitacora.pendientes') }}" method="GET" class="mb-0">
                        {{-- Al filtrar no se pierde a dónde regresa "Volver" --}}
                        @include('partials.origen-ocultos')
                        @if ($verAnteriores)
                            <input type="hidden" name="anteriores" value="1">
                        @endif
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Laboratorio</label>
                                <select name="centro" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach ($centros as $centro)
                                        <option value="{{ $centro->id }}"
                                            {{ (string) request('centro') === (string) $centro->id ? 'selected' : '' }}>
                                            {{ $centro->nombre_centro }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Profesor</label>
                                <select name="profesor" class="form-select select-search">
                                    <option value="">Todos</option>
                                    @foreach ($profesores as $profesor)
                                        <option value="{{ $profesor->id }}"
                                            {{ (string) request('profesor') === (string) $profesor->id ? 'selected' : '' }}>
                                            {{ $profesor->nombre_completo }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label fw-bold text-muted small text-uppercase">Desde</label>
                                <input type="date" name="desde" class="form-control" value="{{ $desde }}"
                                    min="{{ \Carbon\Carbon::parse($semestre->fecha_inicio)->format('Y-m-d') }}"
                                    max="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label fw-bold text-muted small text-uppercase">Hasta</label>
                                <input type="date" name="hasta" class="form-control" value="{{ $hasta }}"
                                    min="{{ \Carbon\Carbon::parse($semestre->fecha_inicio)->format('Y-m-d') }}"
                                    max="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-lg-2 d-flex gap-2">
                                <button type="submit" class="btn bg-marca-green text-white fw-bold px-4 flex-grow-1">
                                    <i class="bi bi-funnel me-1"></i> Filtrar
                                </button>
                                @if (request()->hasAny(['centro', 'profesor', 'desde', 'hasta']))
                                    <a href="{{ route('admin.bitacora.pendientes', $conOrigen) }}"
                                        class="btn btn-outline-danger d-flex align-items-center" title="Limpiar filtros">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $anteriores = count(array_filter($pendientes, fn($p) => $p['anterior']));
                $conAlumnos = count(array_filter($pendientes, fn($p) => $p['alumnos'] > 0));
                $reales = count($pendientes) - $anteriores;

                // Mostrar u ocultar las de antes del registro sin perder filtros ni origen
                $urlAnteriores = fn($ver) => route('admin.bitacora.pendientes', array_filter(
                    array_merge(request()->except('anteriores'), ['anteriores' => $ver ? 1 : null]),
                    fn($v) => $v !== null && $v !== ''
                ));
                $textoAnteriores = 'Son de antes de que el horario se registrara en el sistema. No cuentan en el reporte del profesor mientras estén vacías; al capturarlas, sí.';
            @endphp

            {{-- DESHACER LA ÚLTIMA CAPTURA (durante una hora) --}}
            @if ($ultimaCaptura)
                <div class="alert bg-white border shadow-sm rounded-4 d-flex flex-wrap align-items-center gap-2 mb-3"
                    style="border-left: 5px solid var(--marca-yellow) !important;">
                    <i class="bi bi-arrow-counterclockwise text-marca-black fs-5"></i>
                    <span class="small flex-grow-1">
                        Última captura: <strong>{{ count($ultimaCaptura['ids']) }}
                            {{ count($ultimaCaptura['ids']) == 1 ? 'clase' : 'clases' }}</strong> como
                        <strong>{{ $estados[$ultimaCaptura['estado']] ?? $ultimaCaptura['estado'] }}</strong>,
                        {{ \Carbon\Carbon::parse($ultimaCaptura['hora'])->diffForHumans() }}.
                        ¿Te equivocaste? Puedes deshacerla.
                    </span>
                    <form action="{{ route('admin.bitacora.pendientes.deshacer') }}" method="POST" class="mb-0" id="form-deshacer">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-marca-black rounded-pill fw-bold px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Deshacer
                        </button>
                    </form>
                </div>
            @endif

            @if (empty($pendientes))
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-check2-circle fs-1 text-marca-green opacity-50 d-block mb-3"></i>
                        <h5 class="fw-bold text-dark">No hay clases pendientes</h5>
                        <p class="text-muted small mb-0">Todas las clases de estas fechas tienen la asistencia del profesor
                            capturada.</p>
                        @if ($totalAnteriores && ! $verAnteriores)
                            <p class="text-muted small mt-3 mb-0" title="{{ $textoAnteriores }}">
                                <i class="bi bi-hourglass-split me-1"></i>
                                Hay <strong>{{ $totalAnteriores }}</strong> de antes de registrar el horario, de las hojas de papel.
                                <a href="{{ $urlAnteriores(true) }}" class="text-marca-green fw-bold">Mostrarlas</a>
                            </p>
                        @endif
                    </div>
                </div>
            @else
                {{-- RESUMEN --}}
                <div class="d-flex flex-wrap gap-2 mb-3 small">
                    <span class="badge bg-white text-dark border rounded-pill px-3 py-2 shadow-sm">
                        <i class="bi bi-clipboard me-1 text-marca-green"></i>
                        <strong>{{ $reales }}</strong> {{ $reales == 1 ? 'clase pendiente' : 'clases pendientes' }}
                    </span>
                    @if ($conAlumnos)
                        <span class="badge bg-white text-dark border rounded-pill px-3 py-2 shadow-sm">
                            <i class="bi bi-people me-1 text-marca-green"></i>
                            <strong>{{ $conAlumnos }}</strong> con alumnos registrados ese día (señal de que se dio)
                        </span>
                    @endif
                    {{-- Las de antes del registro: ocultas salvo que se pidan --}}
                    @if ($totalAnteriores)
                        <span class="badge bg-white text-dark border rounded-pill px-3 py-2 shadow-sm"
                            title="{{ $textoAnteriores }}">
                            <i class="bi bi-hourglass-split me-1 text-muted"></i>
                            <strong>{{ $totalAnteriores }}</strong> de antes de registrar el horario
                            @if ($verAnteriores)
                                · <a href="{{ $urlAnteriores(false) }}" class="text-marca-green fw-bold">Ocultarlas</a>
                            @else
                                · <a href="{{ $urlAnteriores(true) }}" class="text-marca-green fw-bold">Mostrarlas</a>
                            @endif
                        </span>
                    @endif
                </div>

                <form action="{{ route('admin.bitacora.pendientes.registrar') }}" method="POST" id="form-pendientes">
                    @csrf

                    {{-- BARRA PARA CAPTURAR VARIAS A LA VEZ --}}
                    <div class="card border-0 shadow-sm rounded-4 mb-3 barra-lote"
                        style="border-left: 5px solid var(--marca-green) !important;">
                        <div class="card-body py-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-lg-auto small fw-bold text-dark">
                                    <span id="contador">0</span> marcadas
                                </div>
                                <div class="col-12 col-md-4 col-lg-3">
                                    <select name="estado" id="estado-lote" class="form-select form-select-sm" required>
                                        <option value="">¿Qué se registra?</option>
                                        @foreach ($estados as $valor => $texto)
                                            <option value="{{ $valor }}">{{ $texto }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md">
                                    <input type="text" name="observaciones" class="form-control form-control-sm"
                                        maxlength="255" placeholder="Observaciones (opcional, se guardan en todas las marcadas)">
                                </div>
                                <div class="col-12 col-md-auto">
                                    <button type="submit" id="boton-registrar"
                                        class="btn bg-marca-green text-white btn-sm rounded-pill fw-bold px-4 w-100" disabled>
                                        <i class="bi bi-check2-circle me-1"></i> Registrar
                                    </button>
                                </div>
                            </div>
                            <div class="text-muted mt-2" style="font-size: .75rem;">
                                <i class="bi bi-envelope-slash me-1"></i>
                                Esta captura no manda correos al profesor: es historial. Si marcas
                                <strong>Falta</strong> o <strong>Justificado</strong>, se quitan las asistencias de alumnos
                                de ese día, igual que en <em>Clases de hoy</em>.
                            </div>
                        </div>
                    </div>

                    {{-- TABLA --}}
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 border-0" style="width: 36px;">
                                            <input class="form-check-input" type="checkbox" id="marcar-todas"
                                                title="Marcar todas">
                                        </th>
                                        <th class="border-0">Fecha</th>
                                        <th class="border-0">Horario</th>
                                        <th class="border-0">Materia / Grupo</th>
                                        <th class="border-0">Profesor</th>
                                        <th class="border-0">Laboratorio</th>
                                        <th class="border-0 text-center">Alumnos ese día</th>
                                        <th class="border-0 text-center pe-4">Lista</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendientes as $p)
                                        @php
                                            $h = $p['horario'];
                                            $f = \Carbon\Carbon::parse($p['fecha']);
                                        @endphp
                                        <tr class="shadow-hover border-bottom border-light">
                                            <td class="ps-4">
                                                <input class="form-check-input casilla" type="checkbox" name="clases[]"
                                                    value="{{ $p['llave'] }}" data-alumnos="{{ $p['alumnos'] }}"
                                                    data-materia="{{ $p['horario']->materia->nombre_materia ?? 'Sin materia' }}"
                                                    data-profesor="{{ $p['horario']->user->nombre_completo ?? 'Sin profesor' }}">
                                            </td>
                                            <td class="text-nowrap">
                                                <span class="fw-bold text-dark">{{ ucfirst($f->locale('es')->isoFormat('ddd D/MM')) }}</span>
                                                @if ($p['anterior'])
                                                    <div class="text-muted" style="font-size: .7rem;"
                                                        title="De antes de que el horario se registrara en el sistema: no cuenta en el reporte mientras esté vacía.">
                                                        <i class="bi bi-hourglass-split me-1"></i>Antes del registro
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-nowrap small">
                                                {{ \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') }} –
                                                {{ \Carbon\Carbon::parse($h->hora_fin)->format('H:i') }}
                                            </td>
                                            <td>
                                                <span class="fw-bold text-dark small">{{ $h->materia->nombre_materia ?? 'Sin materia' }}</span>
                                                <div class="small text-muted">{{ $h->grupo->nombre_grupo ?? 'Sin grupo' }}</div>
                                            </td>
                                            <td class="small text-secondary fw-semibold">
                                                {{ $h->user->nombre_completo ?? 'Sin profesor' }}
                                            </td>
                                            <td class="small">{{ $h->centroComputo->nombre_centro ?? '—' }}</td>
                                            <td class="text-center">
                                                @if ($p['alumnos'] > 0)
                                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3">
                                                        <i class="bi bi-people-fill me-1"></i>{{ $p['alumnos'] }}
                                                    </span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center pe-4">
                                                {{-- Pasar la lista de alumnos de ese día, desde la hoja --}}
                                                <a href="{{ route('admin.bitacora.asistencia', ['id' => $h->id, 'fecha' => $p['fecha'], 'volver' => 'pendientes'] + $conOrigen) }}"
                                                    class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                                                    title="Capturar la lista de alumnos de este día">
                                                    <i class="bi bi-list-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('.select-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                language: {
                    noResults: () => "No se encontraron resultados"
                }
            });
            $(document).on('select2:open', () => {
                document.querySelector('.select2-search__field').focus();
            });

            const casillas = document.querySelectorAll('.casilla');
            const todas = document.getElementById('marcar-todas');
            const contador = document.getElementById('contador');
            const boton = document.getElementById('boton-registrar');
            const estado = document.getElementById('estado-lote');
            const formulario = document.getElementById('form-pendientes');

            function actualizar() {
                const marcadas = document.querySelectorAll('.casilla:checked').length;
                if (contador) contador.textContent = marcadas;
                if (boton) boton.disabled = marcadas === 0;
                casillas.forEach(c => c.closest('tr').classList.toggle('fila-marcada', c.checked));
                if (todas) {
                    todas.checked = marcadas > 0 && marcadas === casillas.length;
                    todas.indeterminate = marcadas > 0 && marcadas < casillas.length;
                }
            }

            casillas.forEach(c => c.addEventListener('change', actualizar));
            todas?.addEventListener('change', function() {
                casillas.forEach(c => c.checked = this.checked);
                actualizar();
            });

            formulario?.addEventListener('submit', function(e) {
                e.preventDefault();

                const marcadas = document.querySelectorAll('.casilla:checked');
                const texto = estado.options[estado.selectedIndex].text;
                const quitaAlumnos = ['falta', 'justificado'].includes(estado.value);
                const conAlumnos = Array.from(marcadas).filter(c => parseInt(c.dataset.alumnos) > 0).length;

                // Los nombres vienen de la base: se escapan antes de ponerlos en el aviso
                const escapar = (t) => String(t).replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                })[c]);

                // De qué materias y profesores son: con "Marcar todas" es fácil llevarse
                // clases que no se querían, y así se ve antes de guardar.
                const porClase = {};
                marcadas.forEach(c => {
                    const clave = `${c.dataset.materia} — ${c.dataset.profesor}`;
                    porClase[clave] = (porClase[clave] || 0) + 1;
                });
                const lista = Object.entries(porClase)
                    .sort((a, b) => b[1] - a[1])
                    .map(([clave, n]) => `<li><strong>${n}</strong> · ${escapar(clave)}</li>`)
                    .join('');

                let detalle = `Se registrará <strong>"${escapar(texto)}"</strong> en <strong>${marcadas.length}</strong> ${marcadas.length === 1 ? 'clase' : 'clases'}:`;
                detalle += `<ul class="text-start small mt-2 mb-2" style="max-height: 180px; overflow-y: auto;">${lista}</ul>`;
                if (quitaAlumnos && conAlumnos > 0) {
                    detalle += `<p class="small text-danger mb-0">${conAlumnos} de ellas tienen asistencias de alumnos, que se quitarán porque la clase no se dio.</p>`;
                }

                // Muchas a la vez: se pide confirmar que se revisó la lista
                const muchas = marcadas.length >= 10;

                Swal.fire({
                    title: '¿Registrar estas clases?',
                    html: detalle,
                    icon: quitaAlumnos && conAlumnos > 0 ? 'warning' : 'question',
                    input: muchas ? 'checkbox' : undefined,
                    inputPlaceholder: muchas ? `Revisé la lista: registrar las ${marcadas.length} clases` : undefined,
                    inputValidator: muchas ? (valor) => !valor && 'Marca la casilla para confirmar que revisaste la lista.' : undefined,
                    showCancelButton: true,
                    confirmButtonColor: '#009B4D',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, registrar',
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        popup: 'rounded-4 shadow',
                        confirmButton: 'rounded-pill px-4 fw-bold',
                        cancelButton: 'rounded-pill px-4 fw-bold'
                    }
                }).then((r) => {
                    if (r.isConfirmed) formulario.submit();
                });
            });

            actualizar();
        });
    </script>
@endpush
