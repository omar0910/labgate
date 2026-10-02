@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Detalles de la Clase')

@section('content')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .btn-marca-yellow {
            background-color: var(--marca-yellow);
            color: var(--marca-black);
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-marca-yellow:hover {
            background-color: #e6d200;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 233, 0, 0.3);
        }

        .date-card {
            border-left: 5px solid var(--marca-green);
            transition: all 0.2s ease;
        }

        .date-card:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- MENSAJES DE ÉXITO (Cuando guardemos una lista nos regresará aquí) --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @include('partials.aviso-error')

        {{-- ENCABEZADO Y BOTÓN VOLVER --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-journal-text text-marca-green me-2"></i> Perfil de la
                    Clase</h3>
                <p class="text-muted small mb-0 mt-1">Gestión de asistencias y asignación de equipos.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ route('admin.bitacora.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la Bitácora
                </a>
            </div>
        </div>

        <div class="row g-4">

            {{-- LADO IZQUIERDO: INFORMACIÓN DE LA CLASE --}}
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4 bg-white sticky-top" style="top: 20px;">
                    <div class="card-body p-4 p-md-5">

                        <div class="text-center mb-4">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                                style="width: 80px; height: 80px;">
                                <i class="bi bi-book text-marca-green" style="font-size: 2.5rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">{{ $horario->materia->nombre_materia }}</h4>
                            <span
                                class="badge bg-marca-green px-3 py-2 rounded-pill fs-6">{{ $horario->grupo->nombre_grupo }}</span>
                        </div>

                        <hr class="text-secondary opacity-25">

                        <div class="mb-3">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Profesor Titular</small>
                            @php
                                $nombreProf = $horario->user->name ?? '';
                                $apeProf = $horario->user->apellido_paterno ?? '';
                                $inicial = $nombreProf ? mb_substr($nombreProf, 0, 1, 'UTF-8') . '. ' : '';
                                $apeFormateado = mb_convert_case(
                                    mb_strtolower($apeProf, 'UTF-8'),
                                    MB_CASE_TITLE,
                                    'UTF-8',
                                );
                                $profesorFinal = trim($inicial . $apeFormateado);
                            @endphp
                            <div class="fs-5 text-dark fw-semibold"><i
                                    class="bi bi-person-badge text-marca-green me-2"></i>{{ $profesorFinal ?: 'Sin Asignar' }}
                            </div>
                        </div>

                        {{-- Todas las sesiones de la asignatura (una por día de clase) --}}
                        @php
                            $laboratorios = $sesiones->map(fn($s) => $s->centroComputo->nombre_centro ?? null)->filter()->unique();
                            $variosLabs = $laboratorios->count() > 1;
                        @endphp
                        <div class="mb-3">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">
                                {{ $sesiones->count() > 1 ? 'Horarios y Laboratorio' : 'Horario y Laboratorio' }}
                            </small>
                            @foreach ($sesiones as $sesion)
                                <div class="fs-6 text-dark mb-1">
                                    <i class="bi bi-calendar-event text-marca-green me-2"></i>{{ $sesion->etiquetaDeSesion() }}
                                    @if ($variosLabs)
                                        <span class="text-muted small">· {{ $sesion->centroComputo->nombre_centro ?? '' }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @unless ($variosLabs)
                                <div class="fs-6 text-dark">
                                    <i class="bi bi-pc-display text-marca-green me-2"></i>{{ $laboratorios->first() ?? 'N/A' }}
                                </div>
                            @endunless
                        </div>

                    </div>
                </div>
            </div>

            {{-- LADO DERECHO: ACCIONES Y FECHAS --}}
            <div class="col-lg-7">

                @php
                    // Obtenemos el día de hoy en español (ej: "Lunes")
                    $diaHoy = ucfirst(\Carbon\Carbon::now()->locale('es')->dayName);

                    // Las sesiones que tocan hoy ya vienen calculadas del controlador
                    $esDiaDeClase = $sesionesDeHoy->isNotEmpty();

                    // "Lunes, Miércoles y Jueves" para el aviso de cuándo se da la clase
                    $diasDeClase = $sesiones->where('tipo_reserva', '!=', 'especial')->pluck('dia_semana')->unique()->values();
                    $fechasReserva = $sesiones->where('tipo_reserva', 'especial')
                        ->map(fn($s) => $s->fecha_especial ? \Carbon\Carbon::parse($s->fecha_especial)->format('d/m/Y') : null)
                        ->filter()->unique()->values();
                    $unir = fn($lista) => $lista->count() > 1
                        ? $lista->slice(0, -1)->implode(', ') . ' y ' . $lista->last()
                        : $lista->implode('');
                @endphp

                {{-- ACCIÓN PRINCIPAL: PASAR LISTA --}}
                <div
                    class="card shadow-sm border-0 rounded-4 bg-white mb-4 border-bottom border-4 {{ $esDiaDeClase ? 'border-marca-yellow' : 'border-secondary' }}">
                    <div class="card-body p-4">

                        @if ($esDiaDeClase)
                            {{-- SI ES DÍA DE CLASE: Botón rápido (uno por sesión, si hoy hay más de una) --}}
                            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">Pase de Lista del Día</h5>
                                    <p class="text-muted small mb-0">Hoy ({{ $diaHoy }}) está programada esta clase.
                                    </p>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    @foreach ($sesionesDeHoy as $sesionHoy)
                                        <a href="{{ route('admin.bitacora.asistencia', ['id' => $sesionHoy->id, 'fecha' => now()->format('Y-m-d')]) }}"
                                            class="btn btn-marca-yellow rounded-pill px-4 py-2 shadow-sm text-nowrap d-flex align-items-center">
                                            <i class="bi bi-clipboard-check fs-5 me-2"></i>
                                            @if ($sesionesDeHoy->count() > 1)
                                                Pasar Lista de {{ \Carbon\Carbon::parse($sesionHoy->hora_inicio)->format('H:i') }}
                                                a {{ \Carbon\Carbon::parse($sesionHoy->hora_fin)->format('H:i') }}
                                            @else
                                                Pasar Lista Hoy
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- SI NO ES DÍA DE CLASE: Advertencia --}}
                            <div class="d-flex flex-column mb-3 pb-3 border-bottom">
                                <h5 class="fw-bold text-dark mb-1"><i
                                        class="bi bi-exclamation-circle-fill text-warning me-2"></i>Hoy no es día de esta
                                    clase</h5>
                                <p class="text-muted small mb-0">
                                    @if ($diasDeClase->isNotEmpty())
                                        Esta clase está programada para los <strong>{{ $unir($diasDeClase) }}</strong>
                                    @else
                                        Esta reserva es para el <strong>{{ $unir($fechasReserva) }}</strong>
                                    @endif
                                    (Hoy es {{ $diaHoy }}).
                                </p>
                            </div>
                        @endif

                        {{-- Lista de otro día: atrasada o reposición. Con la fecha se reconoce
                             sola a qué sesión corresponde; si no cae en ninguno de sus días, se
                             pregunta a cuál anotarla. --}}
                        <form onsubmit="event.preventDefault(); irAFechaManual();">
                            <label class="form-label small fw-bold text-muted text-uppercase mb-1">Pasar lista atrasada
                                o reposición:</label>
                            <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-secondary text-muted"><i
                                        class="bi bi-calendar-event"></i></span>
                                <input type="date" id="fecha_manual" class="form-control border-secondary" required
                                    max="{{ now()->format('Y-m-d') }}" onchange="elegirSesionPorFecha()">
                                @if ($sesiones->count() > 1)
                                    <select id="sesion_manual" class="form-select border-secondary" required>
                                        <option value="">Sesión...</option>
                                        @foreach ($sesiones as $sesion)
                                            <option value="{{ $sesion->id }}" data-dia="{{ $sesion->numeroDeDia() }}"
                                                data-fecha="{{ $sesion->tipo_reserva === 'especial' ? substr((string) $sesion->fecha_especial, 0, 10) : '' }}"
                                                data-url="{{ route('admin.bitacora.asistencia', ['id' => $sesion->id, 'fecha' => 'FECHA_TEMPORAL']) }}">
                                                {{ $sesion->etiquetaDeSesion() }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" id="sesion_manual" value="{{ $sesiones->first()->id }}"
                                        data-url="{{ route('admin.bitacora.asistencia', ['id' => $sesiones->first()->id, 'fecha' => 'FECHA_TEMPORAL']) }}">
                                @endif
                                <button type="submit" class="btn btn-secondary fw-bold px-3">
                                    Ir a la Lista <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                            <small id="aviso_sesion" class="d-none text-warning-emphasis mt-2"></small>
                        </form>

                        <script>
                            // Al elegir la fecha, se marca sola la sesión de ese día de la semana
                            function elegirSesionPorFecha() {
                                const fecha = document.getElementById('fecha_manual').value;
                                const selector = document.getElementById('sesion_manual');
                                const aviso = document.getElementById('aviso_sesion');

                                if (!fecha || selector.tagName !== 'SELECT') return;

                                const [anio, mes, dia] = fecha.split('-').map(Number);
                                const diaSemana = ((new Date(anio, mes - 1, dia).getDay() + 6) % 7) + 1; // 1 = lunes
                                const coinciden = Array.from(selector.options).filter(o => o.value && (
                                    o.dataset.fecha ? o.dataset.fecha === fecha : Number(o.dataset.dia) === diaSemana
                                ));

                                if (coinciden.length === 1) {
                                    selector.value = coinciden[0].value;
                                    aviso.classList.add('d-none');
                                    aviso.classList.remove('d-block');
                                } else {
                                    selector.value = '';
                                    aviso.textContent = coinciden.length > 1 ?
                                        'Ese día tiene más de una sesión: elige cuál.' :
                                        'Esa fecha no es día de esta clase (reposición): elige a qué sesión anotarla.';
                                    aviso.classList.remove('d-none');
                                    aviso.classList.add('d-block');
                                }
                            }

                            function irAFechaManual() {
                                const fecha = document.getElementById('fecha_manual').value;
                                const selector = document.getElementById('sesion_manual');
                                const sesion = selector.tagName === 'SELECT' ? selector.selectedOptions[0] : selector;

                                if (fecha && sesion && sesion.value) {
                                    // Laravel arma la URL de cada sesión y aquí sólo se le pone la fecha
                                    window.location.href = sesion.dataset.url.replace('FECHA_TEMPORAL', fecha);
                                }
                            }
                        </script>

                    </div>
                </div>

                {{-- HISTORIAL DE FECHAS --}}
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-marca-green me-2"></i>Historial de
                    Sesiones Pasadas</h5>

                @if ($fechasHistorial->isEmpty())
                    <div class="card shadow-sm border-0 rounded-4 bg-light">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar-x fs-1 text-muted opacity-25 d-block mb-3"></i>
                            <h6 class="fw-bold text-dark">No hay registros previos</h6>
                            <p class="text-muted small mb-0">Aún no se ha pasado lista ninguna vez para esta clase.</p>
                        </div>
                    </div>
                @else
                    @php $sesionesPorId = $sesiones->keyBy('id'); @endphp
                    <div class="card shadow-sm border-0 rounded-4 bg-white">
                        <div class="list-group list-group-flush rounded-4">
                            @foreach ($fechasHistorial as $historial)
                                <div
                                    class="list-group-item p-3 date-card d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">
                                            <i class="bi bi-calendar2-check text-marca-green me-2"></i>
                                            {{ \Carbon\Carbon::parse($historial->fecha)->translatedFormat('l, d \d\e F \d\e Y') }}
                                        </h6>
                                        {{-- A qué sesión pertenece la lista, cuando la clase tiene varias --}}
                                        @if ($sesiones->count() > 1 && isset($sesionesPorId[$historial->horario_id]))
                                            <small class="text-muted ms-4 ps-1">
                                                Sesión: {{ $sesionesPorId[$historial->horario_id]->etiquetaDeSesion() }}
                                            </small>
                                        @endif
                                    </div>
                                    <a href="{{ route('admin.bitacora.asistencia', ['id' => $historial->horario_id, 'fecha' => $historial->fecha]) }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">
                                        Ver / Editar <i class="bi bi-pencil-square ms-1"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>

    </div>

@endsection
