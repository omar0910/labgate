{{-- LOGICA DINÁMICA DE PLANTILLA --}}
@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Monitor: ' . $centro->nombre_centro)

@section('content')
    <div class="container-fluid py-4">

        {{-- ======================================================= --}}
        {{-- ESTILOS DEL TEMA (Necesarios para pintar los botones de verde) --}}
        {{-- ======================================================= --}}
        <style>
            .bg-marca-green:hover {
                background-color: #008240 !important;
                color: white !important;
            }

        </style>

        {{-- 1. ENCABEZADO Y LEYENDA --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">

                {{-- Título y Hora --}}
                <div>
                    <h2 class="fw-bold text-dark mb-0">
                        <i class="bi bi-display me-2"></i>{{ $centro->nombre_centro }}
                    </h2>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-clock"></i> Última actualización:
                        <span id="reloj-monitor" class="fw-bold text-dark">--:--:--</span>
                    </div>
                </div>

                {{-- Leyenda de Colores. Antes eran "badges" rellenos que parecían botones
                     (sin serlo); ahora es una leyenda: un punto del color de cada estado. --}}
                <div class="d-flex gap-3 flex-wrap justify-content-center align-items-center small"
                    aria-label="Qué significa cada color">
                    <span class="text-muted fw-bold text-uppercase" style="font-size: .7rem; letter-spacing: 1px;">Colores:</span>
                    <span class="d-flex align-items-center gap-1 text-dark">
                        <span class="rounded-circle bg-light border" style="width: 12px; height: 12px;"></span> Disponible
                    </span>
                    <span class="d-flex align-items-center gap-1 text-dark">
                        <span class="rounded-circle bg-primary" style="width: 12px; height: 12px;"></span> Clase
                    </span>
                    <span class="d-flex align-items-center gap-1 text-dark">
                        <span class="rounded-circle bg-success" style="width: 12px; height: 12px;"></span> Uso Libre
                    </span>
                    <span class="d-flex align-items-center gap-1 text-dark">
                        <span class="rounded-circle bg-danger" style="width: 12px; height: 12px;"></span> Con Falla
                    </span>
                </div>

                {{-- Botón Volver --}}
                <div>
                    <a href="{{ route('monitor.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- MENSAJES DE ALERTA (Éxito / Error de la asignación manual) --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        {{-- También los errores de los formularios (asignar uso libre, captura histórica) --}}
        @include('partials.aviso-error')

        {{-- 2. PANEL DE CONTROL DE LA CLASE ACTUAL --}}
        @if ($claseActual)
            <div
                class="alert alert-primary d-flex justify-content-between align-items-center shadow-sm border-primary mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history fs-3"></i>
                        <div>
                            <h5 class="mb-0 fw-bold">Clase en Curso: {{ $claseActual->materia->nombre_materia }}</h5>
                            <small>
                                {{ date('H:i', strtotime($claseActual->hora_inicio)) }} -
                                {{ date('H:i', strtotime($claseActual->hora_fin)) }}
                                | Prof. {{ $claseActual->user->name }}
                            </small>
                        </div>
                    </div>
                </div>

                @php
                    $rutaLiberar =
                        Auth::user()->rol == 'Administrador'
                            ? route('admin.clase.liberar', $claseActual->id)
                            : route('encargado.clase.liberar', $claseActual->id);
                @endphp

                {{-- MODIFICACIÓN: Se quitó el onsubmit y se agregó ID al form y onclick al botón --}}
                <form id="form-liberar-clase" action="{{ $rutaLiberar }}" method="POST">
                    @csrf
                    <button type="button" onclick="confirmarLiberacion()"
                        class="btn btn-warning fw-bold text-dark border-dark shadow-sm">
                        <i class="bi bi-unlock-fill"></i> Liberar Laboratorio
                    </button>
                </form>
            </div>
        @else
            <div class="alert alert-light border shadow-sm d-flex align-items-center text-muted mb-4">
                <i class="bi bi-cup-hot fs-3 me-3 opacity-50"></i>
                <div>
                    <strong class="text-dark">Tiempo Libre / Uso Libre</strong>
                    <br><small>No hay ninguna clase oficial programada en este momento.</small>
                </div>
            </div>
        @endif

        {{-- Alumnos en clase con su laptop: no ocupan ninguna PC del mapa --}}
        @if ($conEquipoPersonal->isNotEmpty())
            <div class="alert alert-light border shadow-sm mb-4 small">
                <div class="fw-bold text-marca-black mb-1">
                    <i class="bi bi-laptop text-marca-green me-1"></i>
                    Con equipo personal ({{ $conEquipoPersonal->count() }})
                    <span class="fw-normal text-muted">— en clase con su laptop, sin ocupar PC</span>
                </div>
                <div class="text-muted">
                    {{ $conEquipoPersonal->map(fn($a) => trim(($a->user->name ?? '') . ' ' . ($a->user->apellido_paterno ?? '')))->implode(' · ') }}
                </div>
            </div>
        @endif

        {{-- 3. GRID DE COMPUTADORAS --}}
        <div class="row g-3">
            @for ($i = 1; $i <= $totalMaquinas; $i++)
                @php
                    $ocupada = $ocupadas->get($i);
                    $falla = $incidencias->get($i);

                    // AHORA TODAS SON CLICKEABLES
                    $isClickable = true;

                    // ASIGNAMOS EL MODAL CORRECTO SEGÚN EL ESTADO
                    if ($falla) {
                        $modalId = 'modalFalla' . $i;
                    } elseif ($ocupada) {
                        $modalId = 'modalPC' . $i;
                    } else {
                        $modalId = 'modalAsignarUsoLibre';
                    }

                    $cardClass = 'bg-white border text-muted shadow-sm';
                    $icon = 'bi-pc-display';
                    $statusText = 'Disponible';
                    $userText = 'Asignar Alumno';
                    $extraInfo = '';
                    $opacity = '1';

                    if ($falla) {
                        $cardClass = 'bg-danger text-white shadow';
                        $icon = 'bi-exclamation-triangle-fill';
                        $statusText = 'FALLA: ' . Str::limit($falla->categoria, 10);
                        $userText = 'Reportado';
                    } elseif ($ocupada) {
                        if ($ocupada->tipo == 'Uso Libre') {
                            $cardClass = 'bg-success text-white shadow';
                            $icon = 'bi-laptop';
                            $statusText = 'Uso Libre';
                            $userText = Str::limit($ocupada->user->name ?? 'Alumno', 12);
                        } else {
                            $cardClass = 'bg-primary text-white shadow';
                            $icon = 'bi-person-video3';
                            $statusText = Str::limit($ocupada->horario->materia->nombre_materia ?? 'Clase', 12);
                            $userText = Str::limit($ocupada->user->name ?? 'Alumno', 12);
                        }
                    } else {
                        $opacity = '0.5';
                        $extraInfo = 'onclick="prepararAsignacion(' . $i . ')"';
                    }
                @endphp

                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="card h-100 {{ $cardClass }} position-relative text-decoration-none user-select-none hover-card"
                        {!! $extraInfo !!} style="transition: transform 0.2s; cursor: pointer;" data-bs-toggle="modal"
                        data-bs-target="#{{ $modalId }}">

                        <div class="position-absolute top-0 start-0 px-2 py-1 fw-bold small" style="opacity: 0.8;">
                            #{{ $i }}
                        </div>

                        <div
                            class="card-body d-flex flex-column justify-content-center align-items-center text-center p-2 py-3">
                            <i class="bi {{ $icon }} fs-1 mb-2" style="opacity: {{ $opacity }}"></i>
                            <small class="fw-bold text-uppercase lh-1 mb-1" style="font-size: 0.7rem;">
                                {{ $statusText }}
                            </small>
                            <small class="d-block text-truncate w-100" style="font-size: 0.75rem; opacity: 0.9;">
                                {{ $userText }}
                            </small>
                        </div>
                    </div>

                    {{-- MODAL PARA PC OCUPADA --}}
                    @if ($ocupada && !$falla)
                        <div class="modal fade" id="modalPC{{ $i }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <div class="modal-header bg-light border-0">
                                        <h6 class="modal-title fw-bold">Gestionar PC #{{ $i }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <div class="mb-3">
                                            <div class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center border"
                                                style="width: 60px; height: 60px;">
                                                <span
                                                    class="fs-4 fw-bold text-secondary">{{ substr($ocupada->user->name ?? 'A', 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <h6 class="fw-bold mb-0">{{ $ocupada->user->name ?? 'Usuario Desconocido' }}</h6>
                                        <small class="text-muted d-block mb-3">{{ $ocupada->user->email ?? '' }}</small>
                                        <div
                                            class="alert {{ $ocupada->tipo == 'Uso Libre' ? 'alert-success' : 'alert-primary' }} py-2 mb-3 border-0">
                                            <small class="fw-bold">{{ strtoupper($ocupada->tipo) }}</small><br>
                                            <small>Hora Entrada:
                                                {{ \Carbon\Carbon::parse($ocupada->created_at)->format('h:i A') }}</small>
                                        </div>
                                        <form
                                            action="{{ route('monitor.cerrar_sesion', ['id' => $centro->id, 'maquina' => $i]) }}"
                                            method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold">
                                                <i class="bi bi-power"></i> Forzar Salida
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- NUEVO: MODAL PARA PC EN FALLA / MANTENIMIENTO --}}
                    @if ($falla)
                        <div class="modal fade" id="modalFalla{{ $i }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <div class="modal-header bg-danger text-white border-0">
                                        <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            Equipo Bloqueado</h6>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center py-4">
                                        <div class="mb-3">
                                            <i class="bi bi-tools text-danger" style="font-size: 3.5rem;"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark mb-1">PC #{{ $i }}</h5>
                                        <span class="badge bg-danger mb-3 fs-6">{{ $falla->categoria }}</span>
                                        <p class="small text-muted mb-4 px-2">{{ $falla->descripcion }}</p>

                                        <div class="alert alert-light border small text-start mb-0 rounded-3 text-muted">
                                            <i class="bi bi-info-circle text-primary me-1"></i> Para habilitar esta PC, ve
                                            a la <strong>Mesa de Ayuda</strong> y marca el ticket como resuelto.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- NUEVO: MODAL GLOBAL CON PESTAÑAS (EN VIVO / HISTÓRICO) --}}
    {{-- ================================================================= --}}
    <div class="modal fade" id="modalAsignarUsoLibre" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill"></i> Asignar Uso Libre (PC <span
                            id="lbl-num-maquina-header">#0</span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">

                    {{-- Pestañas de Navegación --}}
                    <style>
                        #asignacionTabs .nav-link {
                            color: #6c757d !important;
                            background-color: #f8f9fa !important;
                            border: 1px solid #dee2e6;
                            border-bottom: none;
                        }

                        #asignacionTabs .nav-link.active {
                            color: #009B4D !important;
                            background-color: #ffffff !important;
                            border-bottom-color: #ffffff !important;
                        }
                    </style>
                    <ul class="nav nav-tabs nav-fill border-bottom-0" id="asignacionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="vivo-tab" data-bs-toggle="tab"
                                data-bs-target="#vivo-pane" type="button" role="tab">
                                <i class="bi bi-clock-fill me-1"></i> Registro de hoy
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="historico-tab" data-bs-toggle="tab"
                                data-bs-target="#historico-pane" type="button" role="tab">
                                <i class="bi bi-journal-text me-1"></i> Registro de Bitácora
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-3" id="asignacionTabsContent">

                        {{-- ========================================== --}}
                        {{-- PESTAÑA 1: ASIGNACIÓN EN VIVO (Actual) --}}
                        {{-- ========================================== --}}
                        <div class="tab-pane fade show active" id="vivo-pane" role="tabpanel" tabindex="0">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Matrícula del Alumno:</label>

                                {{-- NUEVO ESTILO DEL BUSCADOR (EN VIVO) --}}
                                <div class="d-flex gap-2">
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                        style="border: 1px solid #ced4da; flex-grow: 1;">
                                        <span class="input-group-text bg-white border-0 text-marca-green">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" id="input-matricula"
                                            class="form-control border-0 search-input ps-0" placeholder="Ej: 213110188"
                                            autocomplete="off">
                                        <button type="button" class="btn bg-marca-green text-white fw-bold px-3 border-0"
                                            onclick="buscarAlumno('vivo')">
                                            Buscar
                                        </button>
                                    </div>
                                </div>
                                <small id="error-busqueda" class="text-danger mt-1 d-none">Alumno no encontrado.</small>
                            </div>

                            <div id="resultado-alumno" class="card border-success bg-light d-none mb-3">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-check-fill fs-1 text-success me-3"></i>
                                        <div>
                                            <h6 id="res-nombre" class="mb-0 fw-bold">Nombre del Alumno</h6>
                                            <small id="res-carrera" class="text-muted">Carrera</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('monitor.asignar_uso_libre') }}" method="POST">
                                @csrf
                                <input type="hidden" name="centro_computo_id" value="{{ $centro->id }}">
                                <input type="hidden" name="numero_maquina" class="hidden-maquina-global">
                                <input type="hidden" name="user_id" id="hidden-user-id">

                                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success" id="btn-confirmar" disabled>Asignar
                                        Equipo</button>
                                </div>
                            </form>
                        </div>

                        {{-- ========================================== --}}
                        {{-- PESTAÑA 2: REGISTRO HISTÓRICO (Bitácora) --}}
                        {{-- ========================================== --}}
                        <div class="tab-pane fade" id="historico-pane" role="tabpanel" tabindex="0">
                            <div
                                class="alert alert-secondary bg-secondary bg-opacity-10 border-secondary border-opacity-25 py-2 mb-3 text-dark small">
                                <i class="bi bi-pencil-square me-1"></i> Usa esta opción para transcribir registros pasados
                                de la bitácora física.
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small mb-1">Matrícula del Alumno:</label>

                                {{-- NUEVO ESTILO DEL BUSCADOR (HISTÓRICO) --}}
                                <div class="d-flex gap-2">
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden"
                                        style="border: 1px solid #ced4da; flex-grow: 1;">
                                        <span class="input-group-text bg-white border-0 text-marca-green">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" id="input-matricula-hist"
                                            class="form-control border-0 search-input ps-0" placeholder="Ej: 213110188"
                                            autocomplete="off">
                                        <button type="button" class="btn bg-marca-green text-white fw-bold px-3 border-0"
                                            onclick="buscarAlumno('hist')">
                                            Buscar
                                        </button>
                                    </div>
                                </div>
                                <small id="error-busqueda-hist" class="text-danger mt-1 d-none">Alumno no
                                    encontrado.</small>
                            </div>

                            <div id="resultado-alumno-hist"
                                class="d-none mb-3 px-2 py-1 bg-light border rounded small fw-bold text-success">
                                <i class="bi bi-check-circle-fill me-1"></i> <span id="res-nombre-hist">Nombre</span>
                            </div>

                            <form action="{{ route('monitor.asignar_uso_libre_historico') }}" method="POST">
                                @csrf
                                <input type="hidden" name="centro_computo_id" value="{{ $centro->id }}">
                                <input type="hidden" name="numero_maquina" class="hidden-maquina-global">
                                <input type="hidden" name="user_id" id="hidden-user-id-hist">

                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <label class="form-label fw-bold small mb-1">Fecha de Uso:</label>
                                        <input type="date" name="fecha" class="form-control form-control-sm"
                                            required max="{{ \Carbon\Carbon::today()->toDateString() }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold small mb-1">Hora Entrada:</label>
                                        <input type="time" name="hora_entrada" class="form-control form-control-sm"
                                            required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold small mb-1">Hora Salida:</label>
                                        <input type="time" name="hora_salida" class="form-control form-control-sm"
                                            required>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success" id="btn-confirmar-hist"
                                        disabled>Guardar Registro</button>
                                </div>
                            </form>
                        </div>

                    </div>

                    {{-- BOTÓN DE MANTENIMIENTO (Para el Admin/Encargado) --}}
                    <div class="bg-light border-top p-3 rounded-bottom text-center">
                        <form action="{{ route('monitor.mantenimiento') }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas bloquear esta PC y enviarla a mantenimiento? Ya no podrá ser asignada a alumnos.');">
                            @csrf
                            <input type="hidden" name="centro_computo_id" value="{{ $centro->id }}">
                            <input type="hidden" name="numero_maquina" class="hidden-maquina-global">

                            <p class="small text-muted mb-2"><i class="bi bi-info-circle"></i> ¿Esta computadora presenta
                                problemas?</p>
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill fw-bold px-4">
                                <i class="bi bi-wrench-adjustable me-1"></i> Bloquear y Enviar a Mantenimiento
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // --- Tooltips Originales ---
            document.addEventListener('DOMContentLoaded', function() {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl)
                });
            });

            // --- Lógica de la nueva alerta para el botón Liberar Laboratorio ---
            function confirmarLiberacion() {
                Swal.fire({
                    title: '¿Finalizar la clase ahora?',
                    text: 'Esto cerrará la sesión de TODOS los alumnos conectados a esta clase inmediatamente.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107', // Color de botón warning
                    cancelButtonColor: '#6c757d', // Color secundario para cancelar
                    confirmButtonText: 'Sí, liberar laboratorio',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Si el usuario confirma, hacemos el submit del formulario mediante su ID
                        document.getElementById('form-liberar-clase').submit();
                    }
                });
            }

            // --- Lógica del Nuevo Modal con Pestañas ---
            function prepararAsignacion(numMaquina) {
                // Resetear Pestaña Vivo
                document.getElementById('input-matricula').value = '';
                document.getElementById('resultado-alumno').classList.add('d-none');
                document.getElementById('error-busqueda').classList.add('d-none');
                document.getElementById('btn-confirmar').disabled = true;
                document.getElementById('hidden-user-id').value = '';

                // Resetear Pestaña Histórico
                document.getElementById('input-matricula-hist').value = '';
                document.getElementById('resultado-alumno-hist').classList.add('d-none');
                document.getElementById('error-busqueda-hist').classList.add('d-none');
                document.getElementById('btn-confirmar-hist').disabled = true;
                document.getElementById('hidden-user-id-hist').value = '';

                // Asignar número de máquina visualmente y a los inputs ocultos de ambos formularios
                document.getElementById('lbl-num-maquina-header').innerText = '#' + numMaquina;
                document.querySelectorAll('.hidden-maquina-global').forEach(el => el.value = numMaquina);

                // Forzar regreso a la primera pestaña por defecto
                const firstTab = new bootstrap.Tab(document.querySelector('#vivo-tab'));
                firstTab.show();

                setTimeout(() => document.getElementById('input-matricula').focus(), 500);
            }

            function buscarAlumno(origen) {
                // Determinar qué inputs usar dependiendo de en qué pestaña estamos ('vivo' o 'hist')
                const suffix = origen === 'hist' ? '-hist' : '';
                const matricula = document.getElementById('input-matricula' + suffix).value.trim();
                const msjError = document.getElementById('error-busqueda' + suffix);
                const btnConfirmar = document.getElementById('btn-confirmar' + suffix);
                const boxResultado = document.getElementById('resultado-alumno' + suffix);

                if (matricula === '') return;

                fetch(`{{ route('monitor.buscar_alumno') }}?matricula=${matricula}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.encontrado) {
                            if (origen === 'vivo') {
                                document.getElementById('res-nombre').innerText = data.nombre + ' ' + data.apellidos;
                                document.getElementById('res-carrera').innerText = data.carrera;
                            } else {
                                document.getElementById('res-nombre-hist').innerText = data.nombre + ' ' + data.apellidos;
                            }

                            document.getElementById('hidden-user-id' + suffix).value = data.id;
                            boxResultado.classList.remove('d-none');
                            msjError.classList.add('d-none');
                            btnConfirmar.disabled = false;
                        } else {
                            boxResultado.classList.add('d-none');
                            msjError.classList.remove('d-none');
                            btnConfirmar.disabled = true;
                            document.getElementById('hidden-user-id' + suffix).value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        msjError.innerText = 'Error de conexión.';
                        msjError.classList.remove('d-none');
                    });
            }

            // Permitir buscar con Enter en ambas pestañas
            document.getElementById('input-matricula').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAlumno('vivo');
                }
            });
            document.getElementById('input-matricula-hist').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAlumno('hist');
                }
            });

            // Script para mostrar la hora local exacta del dispositivo
            document.addEventListener('DOMContentLoaded', function() {
                const now = new Date();
                const horaLocal = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                document.getElementById('reloj-monitor').innerText = horaLocal;
            });
        </script>
    @endpush

@endsection
