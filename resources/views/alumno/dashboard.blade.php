@extends('layouts.alumnos')

{{-- 1. Título dinámico --}}
@section('title', 'Inicio')

@section('content')

    {{-- Los estilos base (Bootstrap, Poppins, iconos y el tema institucional) ya vienen
         del layout; aquí solo van los detalles propios de esta vista. --}}
    <style>
        /* Interruptor de "equipo personal" en el verde institucional (no el azul de Bootstrap) */
        .interruptor-personal:checked {
            background-color: var(--marca-green);
            border-color: var(--marca-green);
        }

        .interruptor-personal:focus {
            border-color: var(--marca-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 155, 77, 0.2);
        }

        /* EFECTO AL PASAR EL MOUSE (Hover) */
        .btn-marca-green:hover:not(:disabled) {
            background-color: #007a3d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 155, 77, 0.25) !important;
        }

        /* EFECTO CUANDO ESTÁ DESHABILITADO */
        .btn-marca-green:disabled {
            background-color: #a5d8bd;
            /* Un verde más apagado */
            color: #ffffff;
            cursor: not-allowed;
            transform: none;
            box-shadow: none !important;
        }
    </style>

    {{-- ENCABEZADO MODERNO --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">¡Hola, {{ $alumno->name }}! </h2>
            <p class="text-muted mb-0">
                Tu horario para el <strong
                    class="text-dark">{{ $fechaMostrada->isoFormat('dddd, D [de] MMMM [de] Y') }}</strong>
            </p>
        </div>
        <div class="mt-3 mt-md-0 text-md-end">
            <span class="badge bg-marca-black text-marca-yellow fs-6 px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-clock me-1"></i> <span id="reloj-actual">--:--:--</span>
            </span>
        </div>
    </div>

    {{-- ALERTAS --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-4 border-success rounded-3"
            role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                <div>
                    <strong>¡Excelente!</strong><br>
                    {{ session('success') }}
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-4 border-danger rounded-3"
            role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">¡Atención!</h6>
                    <p class="mb-0 small">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- GRID DE DOS COLUMNAS (MEJORA DE UX MANTENIDA) --}}
    <div class="row g-4">

        {{-- COLUMNA IZQUIERDA: BUSCADOR DE FECHA Y CLASES (Ocupa 8 de 12 espacios) --}}
        <div class="col-lg-8">

            {{-- BUSCADOR DE FECHA --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4 bg-light">
                <div class="card-body py-3 px-4">
                    <form action="{{ route('alumno.dashboard') }}" method="GET"
                        class="d-flex align-items-center flex-wrap gap-3">
                        <label for="fecha" class="form-label fw-bold mb-0 text-dark"><i
                                class="bi bi-calendar-event me-1"></i> Ver otra fecha:</label>
                        <div class="input-group flex-nowrap" style="max-width: 300px;">
                            <input type="date" class="form-control border-secondary" id="fecha" name="fecha"
                                value="{{ $fechaMostrada->format('Y-m-d') }}">
                            <button type="submit" class="btn bg-marca-black text-white"><i class="bi bi-search"></i></button>
                        </div>
                        @if (!$fechaMostrada->isToday())
                            <a href="{{ route('alumno.dashboard') }}"
                                class="btn btn-outline-secondary btn-sm rounded-pill ms-auto">Volver a Hoy</a>
                        @endif
                    </form>
                </div>
            </div>

            {{-- LISTA DE CLASES --}}
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-bookmark-fill me-2 text-marca-green"></i> Mis Clases</h5>

            <div class="row g-3">
                @forelse ($horariosHoy as $horario)
                    <div class="col-md-6">
                        {{-- Borde de color según estado --}}
                        @php
                            $bordeCard = 'border-secondary';
                            if ($horario->estado_asistencia == 'registrada') {
                                $bordeCard = 'border-success';
                            }
                            if ($horario->estado_asistencia == 'falta') {
                                $bordeCard = 'border-danger';
                            }
                            if ($horario->estado_asistencia == 'activa') {
                                $bordeCard = 'border-marca-green'; // Verde institucional para clases activas
                            }
                        @endphp

                        <div class="card shadow-sm border-0 border-start border-4 {{ $bordeCard }} rounded-4 h-100">
                            <div class="card-body p-4">
                                <span class="badge bg-light text-dark border float-end mb-2">
                                    <i class="bi bi-clock"></i> {{ date('H:i', strtotime($horario->hora_inicio)) }} -
                                    {{ date('H:i', strtotime($horario->hora_fin)) }}
                                </span>

                                {{-- Nombre de materia en Verde institucional --}}
                                <h5 class="fw-bold text-marca-green mb-1" style="font-size: 1.1rem;">
                                    {{ $horario->materia->nombre_materia }}</h5>
                                <p class="small text-muted mb-3">Grupo: {{ $horario->grupo->nombre_grupo }}</p>

                                <div class="mb-4 small">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-person-video3 text-muted me-2"></i>
                                        <span>Prof: <strong>{{ Str::limit($horario->user->name, 20) }}</strong></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-pc-display text-muted me-2"></i>
                                        <span>Lugar: <strong>{{ $horario->centroComputo->nombre_centro }}</strong></span>
                                    </div>
                                </div>

                                {{-- LÓGICA DE BOTONES Y ESTADOS --}}
                                <div class="mt-auto">
                                    @switch($horario->estado_asistencia)
                                        @case('registrada')
                                            <div class="alert alert-success py-2 mb-2 text-center border-0">
                                                <i class="bi bi-check-circle-fill"></i> Asistencia Registrada
                                            </div>
                                            @if ($horario->mi_asistencia && $horario->mi_asistencia->equipo_personal)
                                                {{-- Con su laptop: no hay PC del laboratorio que reportar --}}
                                                <div class="small text-muted text-center">
                                                    <i class="bi bi-laptop me-1"></i> Con tu equipo personal
                                                </div>
                                            @elseif ($horario->mi_asistencia && $horario->mi_asistencia->numero_maquina)
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill"
                                                    onclick="abrirModalReporte('{{ $horario->centro_computo_id }}', '{{ $horario->mi_asistencia->numero_maquina }}', '{{ $horario->centroComputo->nombre_centro }}')">
                                                    <i class="bi bi-tools"></i> Reportar falla en PC
                                                    #{{ $horario->mi_asistencia->numero_maquina }}
                                                </button>
                                            @endif
                                            @if ($horario->mi_asistencia && $horario->mi_asistencia->comentario)
                                                <div class="small text-muted mt-2 text-center fst-italic">
                                                    "{{ $horario->mi_asistencia->comentario }}"</div>
                                            @endif
                                        @break

                                        @case('justificado')
                                            <div class="alert alert-warning py-2 mb-0 text-center border-0 text-dark">
                                                <i class="bi bi-file-earmark-medical-fill"></i> Falta Justificada
                                            </div>
                                            @if ($horario->mi_asistencia && $horario->mi_asistencia->comentario)
                                                <div class="small text-muted mt-2 text-center fst-italic">
                                                    "{{ $horario->mi_asistencia->comentario }}"</div>
                                            @endif
                                        @break

                                        @case('activa')
                                            @include('alumno.partials.form-asistencia')
                                        @break

                                        @case('sin_pc')
                                            {{-- El pase de lista lo marcó presente sin PC: falta decir en cuál está --}}
                                            <div class="alert bg-light text-dark py-2 mb-2 small border-0">
                                                <i class="bi bi-info-circle-fill text-marca-green"></i>
                                                Ya pasaron lista y apareces <strong>presente</strong>.
                                                Indica en qué PC estás para completar tu registro.
                                            </div>
                                            @include('alumno.partials.form-asistencia')
                                        @break

                                        @case('falta')
                                            <div class="alert alert-danger py-2 mb-0 text-center border-0">
                                                <i class="bi bi-x-circle-fill"></i> Falta
                                            </div>
                                        @break

                                        @case('no_impartida')
                                            {{-- El profesor faltó o justificó: no cuenta como falta del alumno --}}
                                            <div class="alert alert-light border py-2 mb-0 text-center text-muted">
                                                <i class="bi bi-calendar-x"></i> Clase no impartida
                                                <div class="small">
                                                    {{ $horario->profesor_justifico ? 'El profesor justificó su ausencia.' : 'El profesor no asistió.' }}
                                                </div>
                                            </div>
                                        @break

                                        @case('sin_registro')
                                            {{-- Ese día nadie quedó registrado: no se sabe si asistió --}}
                                            <div class="alert alert-light border py-2 mb-0 text-center text-muted">
                                                <i class="bi bi-dash-circle"></i> Sin registro de asistencia
                                            </div>
                                        @break

                                        @case('temprano')
                                            <button class="btn btn-light border w-100 text-muted" disabled>
                                                <i class="bi bi-hourglass-split"></i> Abre a las
                                                {{ $horario->ventana_inicio_str }}
                                            </button>
                                        @break

                                        @case('tarde')
                                            <button class="btn btn-light border w-100 text-muted" disabled>
                                                <i class="bi bi-door-closed"></i> Cerrada ({{ $horario->ventana_fin_str }})
                                            </button>
                                            {{-- La clase ya terminó y no tiene registro: que no crea que quedó registrado --}}
                                            <div class="small text-muted text-center mt-2">
                                                <i class="bi bi-exclamation-circle text-danger me-1"></i>
                                                No registraste asistencia en esta clase.
                                            </div>
                                        @break

                                        @case('futuro')
                                            <button class="btn btn-light border w-100 text-muted" disabled>Clase futura</button>
                                        @break

                                        @default
                                            <button class="btn btn-light border w-100 text-muted" disabled>No disponible</button>
                                    @endswitch
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                                <div class="card-body">
                                    @if ($fueraDePeriodo)
                                        {{-- La fecha consultada no pertenece al semestre activo --}}
                                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3"
                                            style="width: 80px; height: 80px;">
                                            <i class="bi bi-calendar-x fs-1 text-marca-yellow"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Fuera del periodo de clases</h5>
                                        <p class="text-muted mb-0 mx-auto" style="max-width: 460px;">
                                            @if ($semestreActivo)
                                                El semestre en curso es
                                                <strong class="text-marca-green">{{ $semestreActivo->nombre }}</strong>
                                                ({{ \Carbon\Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') }}
                                                al {{ \Carbon\Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') }}).
                                                La fecha que consultaste queda fuera de ese rango.
                                            @else
                                                Todavía no hay un periodo escolar activo en el sistema.
                                            @endif
                                        </p>
                                    @else
                                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3"
                                            style="width: 80px; height: 80px;">
                                            <i class="bi bi-calendar-x fs-1 text-secondary"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Día Libre</h5>
                                        <p class="text-muted mb-0">No tienes clases programadas en laboratorios para este día.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUMNA DERECHA: WIDGET DE USO LIBRE (Ocupa 4 de 12 espacios) --}}
            <div class="col-lg-4">
                {{-- Panel pegajoso (Sticky) --}}
                <div class="sticky-top" style="top: 20px; z-index: 10;">
                    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                        {{-- Cabecera del Widget estilo Institucional (Negro institucional y texto Amarillo institucional) --}}
                        <div class="card-header bg-marca-black border-bottom-0 py-3">
                            <h5 class="mb-0 fw-bold d-flex align-items-center text-white">
                                <i class="bi bi-laptop text-marca-yellow fs-4 me-2"></i> Uso Libre
                            </h5>
                        </div>

                        <div class="card-body p-4 bg-white">
                            <p class="small text-muted mb-4">¿Necesitas hacer tareas o proyectos fuera de tu horario? Registra
                                tu equipo aquí.</p>

                            @if ($sesionUsoLibre && is_null($sesionUsoLibre->fecha_hora_salida))
                                {{-- SESIÓN ACTIVA --}}
                                <div class="text-center py-3">
                                    <div class="spinner-grow text-success spinner-grow-sm mb-2" role="status"></div>
                                    <h4 class="text-success fw-bold mb-1">Sesión Activa</h4>
                                    <p class="text-dark mb-4">Estás usando la <strong class="fs-5">PC
                                            #{{ $sesionUsoLibre->numero_maquina }}</strong></p>

                                    <form action="{{ route('alumno.terminar-uso-libre') }}" method="POST" class="mb-3">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-danger w-100 rounded-pill fw-bold py-2 shadow-sm">
                                            <i class="bi bi-power"></i> Terminar Sesión
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-link text-danger p-0 small text-decoration-none"
                                        onclick="abrirModalReporte('{{ $sesionUsoLibre->centro_computo_id }}', '{{ $sesionUsoLibre->numero_maquina }}', 'Laboratorio (Uso Libre)')">
                                        <i class="bi bi-exclamation-triangle"></i> Reportar falla en mi equipo
                                    </button>
                                </div>
                            @else
                                {{-- FORMULARIO DE USO LIBRE --}}
                                @if ($sesionUsoLibre && $sesionUsoLibre->fecha_hora_salida)
                                    <div
                                        class="alert alert-light border border-secondary text-center py-2 px-3 small mb-4 rounded-3">
                                        <i class="bi bi-clock-history text-muted"></i> Tu última sesión finalizó a las:
                                        <strong
                                            class="text-dark">{{ \Carbon\Carbon::parse($sesionUsoLibre->fecha_hora_salida)->format('h:i A') }}</strong>
                                    </div>
                                @endif

                                @if ($fechaMostrada->isToday() && $equipoDetectado && ! $equipoDetectado['laboratorio']->permite_uso_libre)
                                    {{-- Está en una PC de un laboratorio sin uso libre --}}
                                    <div class="alert alert-light border text-center rounded-3 small mb-0">
                                        <i class="bi bi-pc-display-horizontal fs-3 d-block mb-2 text-muted"></i>
                                        Estás en una PC de <strong>{{ $equipoDetectado['laboratorio']->nombre_centro }}</strong>,
                                        que no tiene Uso Libre.
                                        @if ($laboratoriosUsoLibre->isNotEmpty())
                                            <span class="d-block mt-1">El Uso Libre es en:
                                                <strong>{{ $laboratoriosUsoLibre->pluck('nombre_centro')->implode(', ') }}</strong>.</span>
                                        @endif
                                    </div>
                                @elseif ($fechaMostrada->isToday() && $equipoDetectado)
                                    {{-- La PC se detectó sola: el laboratorio y el número ya están puestos --}}
                                    <form id="form-uso-libre" action="{{ route('alumno.uso-libre') }}" method="POST"
                                        data-lab-nombre="{{ $equipoDetectado['laboratorio']->nombre_centro }}">
                                        @csrf
                                        <input type="hidden" name="centro_computo_id" value="{{ $equipoDetectado['centro'] }}">
                                        <input type="hidden" name="numero_maquina" id="pc-input" value="{{ $equipoDetectado['maquina'] }}">

                                        <div class="alert bg-light border rounded-3 small mb-4 text-center">
                                            <i class="bi bi-pc-display-horizontal text-marca-green fs-4 d-block mb-1"></i>
                                            Estás en la <strong>PC #{{ $equipoDetectado['maquina'] }}</strong> de
                                            <strong>{{ $equipoDetectado['laboratorio']->nombre_centro }}</strong>.
                                        </div>

                                        <button type="button"
                                            class="btn btn-marca-green w-100 rounded-pill py-2 shadow-sm d-flex align-items-center justify-content-center fw-bold gap-2"
                                            onclick="confirmarUsoLibre()">
                                            Registrar Entrada <i class="bi bi-box-arrow-in-right"></i>
                                        </button>
                                    </form>
                                @elseif ($fechaMostrada->isToday() && $laboratoriosUsoLibreManual->isEmpty() && $laboratoriosUsoLibre->isNotEmpty())
                                    {{-- Desde una laptop: el uso libre es para las PCs del centro --}}
                                    <div class="alert alert-light border text-center rounded-3 small mb-0">
                                        <i class="bi bi-pc-display-horizontal fs-3 d-block mb-2 text-muted"></i>
                                        El Uso Libre se registra desde una de las computadoras de
                                        <strong>{{ $laboratoriosUsoLibre->pluck('nombre_centro')->implode(', ') }}</strong>,
                                        no desde un equipo personal.
                                    </div>
                                @elseif ($fechaMostrada->isToday())
                                    <form id="form-uso-libre" action="{{ route('alumno.uso-libre') }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-dark">1. Selecciona
                                                Laboratorio:</label>
                                            <select name="centro_computo_id" id="lab-select"
                                                class="form-select border-secondary" required>
                                                @forelse($laboratoriosUsoLibreManual as $index => $lab)
                                                    {{-- 
                                                    Si es el primer elemento del ciclo ($index == 0), le ponemos 'selected'.
                                                    Esto hace que el Laboratorio 1 (o el que esté en BD) aparezca por defecto.
                                                --}}
                                                    <option value="{{ $lab->id }}" {{ $index == 0 ? 'selected' : '' }}>
                                                        {{ $lab->nombre_centro }} ({{ $lab->capacidad }} PCs)
                                                    </option>
                                                @empty
                                                    <option value="" disabled selected>No hay laboratorios disponibles
                                                    </option>
                                                @endforelse
                                            </select>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold small text-dark">2. Número de tu equipo:</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-secondary text-muted">#</span>
                                                <input type="number" name="numero_maquina" id="pc-input"
                                                    class="form-control border-secondary" required min="1"
                                                    placeholder="Ej: 15">
                                            </div>
                                        </div>

                                        <button type="button"
                                            class="btn btn-marca-green w-100 rounded-pill py-2 shadow-sm d-flex align-items-center justify-content-center fw-bold gap-2"
                                            onclick="confirmarUsoLibre()" @if ($laboratoriosUsoLibreManual->isEmpty()) disabled @endif>
                                            Registrar Entrada <i class="bi bi-box-arrow-in-right"></i>
                                        </button>
                                    </form>
                                @else
                                    <div class="alert alert-warning text-center rounded-3 border-0"
                                        style="background-color: #fff9cc; color: #856a00;">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-75"></i>
                                        <strong class="d-block mb-1">No disponible hoy</strong>
                                        <small>Solo puedes registrar Uso Libre seleccionando la fecha actual.</small>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    @endsection

    @push('scripts')
        <script>
            // --- RELOJ ---
            function actualizarReloj() {
                const now = new Date();
                let hora = now.getHours();
                const ampm = hora >= 12 ? 'PM' : 'AM';
                hora = hora % 12;
                hora = hora ? hora : 12;
                const minutos = now.getMinutes().toString().padStart(2, '0');
                const segundos = now.getSeconds().toString().padStart(2, '0');
                document.getElementById('reloj-actual').textContent = `${hora}:${minutos}:${segundos} ${ampm}`;
            }
            setInterval(actualizarReloj, 1000);
            actualizarReloj();

            // --- EQUIPO PERSONAL ---
            // Al marcarlo, el número de PC deja de pedirse: la asistencia se registra
            // con su laptop, sin ocupar una computadora del laboratorio.
            document.querySelectorAll('.interruptor-personal').forEach(function(interruptor) {
                interruptor.addEventListener('change', function() {
                    const campo = this.closest('form').querySelector('.campo-pc');
                    campo.disabled = this.checked;
                    campo.required = !this.checked;
                    campo.value = '';
                    campo.placeholder = this.checked ? 'Personal' : 'Ej: 5';
                });
            });

            // --- CONFIRMAR USO LIBRE ---
            function confirmarUsoLibre() {
                const formulario = document.getElementById('form-uso-libre');
                const labSelect = document.getElementById('lab-select');
                const pcInput = document.getElementById('pc-input');

                // Si la PC se detectó sola no hay selector: el laboratorio viene en el formulario
                if ((labSelect && !labSelect.value) || !pcInput.value) {
                    Swal.fire('Faltan datos', 'Por favor selecciona un laboratorio y el número de PC.', 'info');
                    return;
                }

                const labNombre = labSelect ? labSelect.options[labSelect.selectedIndex].text : formulario.dataset.labNombre;

                Swal.fire({
                    title: '¿Registrar Entrada?',
                    html: `Vas a ocupar el equipo <strong>#${pcInput.value}</strong> <br> en <strong>${labNombre}</strong>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#009B4D', // Verde institucional para el botón de confirmar en el popup
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, Registrar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('form-uso-libre').submit();
                    }
                });
            }

            // --- REPORTE DE FALLA ---
            function abrirModalReporte(centroId, numMaquina, nombreLab) {
                Swal.fire({
                    title: 'Reportar Falla',
                    html: `
            <div class="text-start mb-3 bg-light p-3 rounded">
                <small class="text-muted d-block">Lugar:</small> <strong>${nombreLab}</strong><br>
                <small class="text-muted d-block mt-1">Equipo:</small> <strong>PC #${numMaquina}</strong>
            </div>
            <form id="form-reporte" action="{{ route('alumno.reportar-incidencia') }}" method="POST">
                @csrf
                <input type="hidden" name="centro_computo_id" value="${centroId}">
                <input type="hidden" name="numero_maquina" value="${numMaquina}">
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold">¿Qué está fallando?</label>
                    <select name="categoria" class="form-select border-secondary" required>
                        <option value="" disabled selected>Selecciona una opción...</option>
                        <option value="Mouse">🖱️ Mouse</option>
                        <option value="Teclado">⌨️ Teclado</option>
                        <option value="Monitor">🖥️ Monitor / Pantalla</option>
                        <option value="Internet">🌐 Internet / Red</option>
                        <option value="Software">💿 Software / Programas</option>
                        <option value="Encendido">⚡ No enciende</option>
                        <option value="Otro">❓ Otro</option>
                    </select>
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold">Detalles (Opcional)</label>
                    <textarea name="descripcion" class="form-control border-secondary" rows="2" placeholder="Ej: La pantalla parpadea..."></textarea>
                </div>
            </form>`,
                    showCancelButton: true,
                    confirmButtonText: 'Enviar Reporte',
                    confirmButtonColor: '#d33',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const form = document.getElementById('form-reporte');
                        if (!form.checkValidity()) {
                            Swal.showValidationMessage('Por favor selecciona una categoría');
                            return false;
                        }
                        form.submit();
                    }
                });
            }
        </script>
    @endpush
