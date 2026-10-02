@extends('layouts.profesor')
{{-- El nombre del grupo ya incluye el de la materia: repetirlos hacía un título larguísimo --}}
@section('title', $horario->materia->nombre_materia)

{{-- La lista de hoy es de "Clases de Hoy"; la de otro día, del "Historial" --}}
@section('menu', \Carbon\Carbon::parse($fecha)->isToday() ? 'hoy' : 'historial')

@section('content')

    {{-- ESTILOS INSTITUCIONALES Y DE LA TABLA --}}
    <style>
        /* Estados activos personalizados para los botones de enviar */
        .btn-check:checked+.btn-outline-success {
            background-color: var(--marca-green) !important;
            border-color: var(--marca-green) !important;
            color: white !important;
        }

        .btn-check:checked+.btn-outline-danger {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }

        .btn-check:checked+.btn-outline-warning {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: black !important;
        }

        /* Barra de guardado flotante (Sticky Footer) */
        .sticky-footer-bar {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
            z-index: 100;
            border-radius: 0 0 1rem 1rem;
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-card-checklist text-marca-green me-2"></i> Pase de Lista
                </h3>
                <p class="text-muted small mb-1 mt-1">
                    <span class="fw-bold">{{ $horario->materia->nombre_materia }}</span> •
                    <span class="text-marca-green fw-bold">{{ ucfirst(\Carbon\Carbon::parse($fecha)->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
                </p>

                <p class="text-muted small mb-0">
                    <span class="badge bg-light text-dark border"><i class="bi bi-people me-1"></i> Grupo:
                        {{ $horario->grupo->nombre_grupo }}</span>
                </p>
            </div>

            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="{{ route('profesor.ver-reporte', ['horario_id' => $horario->id, 'seccion' => \Carbon\Carbon::parse($fecha)->isToday() ? 'hoy' : 'historial', 'fecha' => $fecha]) }}"
                    class="btn btn-outline-marca-green rounded-pill shadow-sm px-4 fw-bold">
                    <i class="bi bi-bar-chart-fill me-1"></i> Estadísticas
                </a>

                @if (\Carbon\Carbon::parse($fecha)->isToday())
                    <a href="{{ route('profesor.dashboard') }}"
                        class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                @else
                    {{-- Al historial, justo en esta clase (queda resaltada) --}}
                    <a href="{{ route('profesor.historial', ['semestre_id' => $horario->semestre_id]) }}#clase-{{ $horario->id }}-{{ $fecha }}"
                        class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Si algo impidió guardar, que se vea (antes no se mostraba y parecía que no pasaba nada) --}}
        @if (session('error') || $errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No se guardó la lista.</strong>
                {{ session('error') ?? $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- La clase está registrada como no impartida: los alumnos no pueden registrarse en ella --}}
        @if (in_array($estadoProfesor, ['falta', 'justificado'], true))
            <div class="alert alert-warning rounded-4 shadow-sm border-0 border-start border-4 border-warning">
                <i class="bi bi-calendar-x me-2"></i>
                Esta clase está registrada como <strong>{{ $estadoProfesor === 'justificado' ? 'falta justificada' : 'falta' }}
                    tuya</strong>, así que los alumnos no pueden registrarse en ella. Si sí la diste, avisa al encargado del
                centro de cómputo para que lo corrija.
            </div>
        @endif

        {{-- FORMULARIO MAESTRO --}}
        <form action="{{ route('profesor.guardar-asistencia') }}" method="POST" id="formAsistencia">
            @csrf
            <input type="hidden" name="horario_id" value="{{ $horario->id }}">
            <input type="hidden" name="fecha" value="{{ $fecha }}">

            <div class="card shadow-sm border-0 rounded-4 bg-white mb-5">

                @php
                    // Quienes todavía no tienen registro: salen sin marcar (antes, "presente" por omisión)
                    $sinRegistro = $alumnos->reject(fn($a) => $asistencias->has($a->id))->count();
                @endphp
                <div
                    class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="fw-bold text-dark mb-0">Lista de Alumnos ({{ $alumnos->count() }})</h6>
                    <div class="d-flex flex-wrap gap-2">
                        {{-- Al terminar la clase: los que siguen sin marcar, falta (en un clic) --}}
                        @if ($sinRegistro > 0)
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                                id="boton-falta-sin-marcar" onclick="marcarSinMarcarComoFalta()"
                                title="Marca como falta a los alumnos que todavía no tienen asistencia marcada">
                                <i class="bi bi-person-x me-1"></i> Falta a los no marcados (<span data-cuenta="sin_marcar_boton">{{ $sinRegistro }}</span>)
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold"
                            onclick="marcarTodosPresentes()">
                            <i class="bi bi-check-all me-1"></i> Marcar todos "Presente"
                        </button>
                    </div>
                </div>

                {{-- Cuántos van en cada estado (se actualiza al marcar) --}}
                @if ($alumnos->count() > 0)
                    <div class="px-4 pt-3 d-flex flex-wrap gap-2 small" id="resumen-lista">
                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                            <i class="bi bi-check-lg me-1"></i>Presentes: <span data-cuenta="presente">0</span>
                        </span>
                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2">
                            <i class="bi bi-x-lg me-1"></i>Faltas: <span data-cuenta="falta">0</span>
                        </span>
                        <span class="badge rounded-pill bg-warning bg-opacity-10 border border-warning border-opacity-25 px-3 py-2"
                            style="color: #b58800;">
                            <i class="bi bi-shield-exclamation me-1"></i>Justificados: <span data-cuenta="justificado">0</span>
                        </span>
                        @if ($sinRegistro > 0)
                            <span class="badge rounded-pill bg-light text-muted border px-3 py-2" id="cuenta-sin-marcar">
                                <i class="bi bi-hourglass me-1"></i>Sin marcar: <span data-cuenta="sin_marcar">{{ $sinRegistro }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- Qué pasa con quien se quede sin marcar --}}
                    @if ($sinRegistro > 0)
                        <p class="px-4 pt-2 mb-0 small text-muted">
                            <i class="bi bi-info-circle text-marca-green me-1"></i>
                            Quien no tiene registro aparece sin marcar.
                            @if ($claseEnCurso)
                                Si lo dejas así, todavía puede registrarse mientras dure la clase; si no lo hace, contará como falta.
                            @else
                                Si lo dejas así, cuenta como falta.
                            @endif
                        </p>
                    @endif
                @endif

                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    {{-- Ajuste de porcentajes para que quepa la columna carrera --}}
                                    {{-- En celular, # / matrícula / PC van debajo del nombre para que quepan los botones --}}
                                    <th class="ps-4 border-0 d-none d-md-table-cell" style="width: 5%;">#</th>
                                    <th class="border-0 d-none d-md-table-cell" style="width: 10%;">Matrícula</th>
                                    <th class="border-0 ps-3 ps-md-2" style="width: 20%;">Nombre del Alumno</th>
                                    {{-- En pantallas chicas no cabe: se oculta --}}
                                    <th class="border-0 d-none d-lg-table-cell" style="width: 15%;">Carrera</th>
                                    <th class="text-center border-0" style="width: 25%;">Asistencia</th>
                                    <th class="text-center border-0 d-none d-md-table-cell" style="width: 12%;">No. PC</th>
                                    <th class="text-center pe-3 pe-md-4 border-0" style="width: 13%;"><span class="d-none d-md-inline">Observaciones</span><span class="d-md-none">Nota</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($alumnos as $index => $alumno)
                                    @php
                                        $registro = $asistencias->get($alumno->id);
                                        // Si no hay registro, sin marcar: el profesor decide. Antes era
                                        // "presente", y guardar la lista a media clase le daba asistencia
                                        // a quien no había llegado.
                                        $estado = $registro ? $registro->estado : null;
                                        $comentario = $registro ? $registro->comentario : '';
                                        $numero_maquina = $registro ? $registro->numero_maquina : null;

                                        $apePaterno = $alumno->apellido_paterno ?? '';
                                        $apeMaterno = $alumno->apellido_materno ?? '';
                                        $nombreCompleto = trim($apePaterno . ' ' . $apeMaterno . ' ' . $alumno->name);
                                    @endphp

                                    <tr class="border-bottom border-light fila-alumno" data-sin-registro="{{ $registro ? 0 : 1 }}">
                                        <td class="ps-4 text-muted fw-bold d-none d-md-table-cell">{{ $index + 1 }}</td>

                                        <td class="d-none d-md-table-cell">
                                            <span
                                                class="fw-bold text-dark font-monospace fs-6">{{ $alumno->matricula ?? 'S/M' }}</span>
                                        </td>

                                        <td class="fw-semibold text-dark text-capitalize ps-3 ps-md-2">
                                            {{ mb_strtolower($nombreCompleto, 'UTF-8') }}
                                            <span class="d-md-none d-block small text-muted fw-normal" style="text-transform: none;">
                                                {{ $alumno->matricula ?? 'S/M' }}
                                                @if ($registro && $registro->equipo_personal)
                                                    · <i class="bi bi-laptop"></i> Personal
                                                @elseif ($numero_maquina)
                                                    · <span class="text-marca-green fw-bold">PC-{{ str_pad($numero_maquina, 2, '0', STR_PAD_LEFT) }}</span>
                                                @elseif (! $registro)
                                                    · <i class="bi bi-hourglass"></i> Sin registro
                                                @endif
                                            </span>
                                        </td>

                                        {{-- Celda independiente para Carrera --}}
                                        <td class="text-muted small d-none d-lg-table-cell">
                                            {{ $alumno->carrera ?? 'Sin asignar' }}
                                        </td>

                                        {{-- Toggles de Asistencia --}}
                                        <td class="text-center">
                                            <div class="btn-group shadow-sm" role="group">
                                                {{-- PRESENTE --}}
                                                <input type="radio" class="btn-check radio-presente"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="presente_{{ $alumno->id }}" value="presente"
                                                    {{ $estado == 'presente' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-success btn-sm px-3"
                                                    for="presente_{{ $alumno->id }}" title="Presente">
                                                    <i class="bi bi-check-lg"></i>
                                                </label>

                                                {{-- FALTA --}}
                                                <input type="radio" class="btn-check"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="falta_{{ $alumno->id }}" value="falta"
                                                    {{ $estado == 'falta' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-danger btn-sm px-3"
                                                    for="falta_{{ $alumno->id }}" title="Falta">
                                                    <i class="bi bi-x-lg"></i>
                                                </label>

                                                {{-- JUSTIFICADO --}}
                                                <input type="radio" class="btn-check"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="justificado_{{ $alumno->id }}" value="justificado"
                                                    {{ $estado == 'justificado' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-warning btn-sm px-3"
                                                    for="justificado_{{ $alumno->id }}" title="Justificado">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                </label>
                                            </div>
                                        </td>

                                        {{-- NÚMERO DE PC (Solo Lectura) --}}
                                        <td class="text-center d-none d-md-table-cell">
                                            @if ($registro && $registro->equipo_personal)
                                                {{-- Trabajó en su laptop: no ocupó PC del laboratorio --}}
                                                <span
                                                    class="badge bg-light border text-marca-black fs-6 fw-bold shadow-sm px-3 py-2">
                                                    <i class="bi bi-laptop me-1"></i>Personal
                                                </span>
                                            @elseif ($numero_maquina)
                                                <span
                                                    class="badge bg-light border text-marca-green fs-6 fw-bold shadow-sm px-3 py-2">
                                                    PC-{{ str_pad($numero_maquina, 2, '0', STR_PAD_LEFT) }}
                                                </span>
                                            @elseif (! $registro)
                                                {{-- Aún no se registra (por eso sale sin marcar) --}}
                                                <span class="badge bg-light border text-muted fw-semibold px-2 py-1"
                                                    title="Todavía no registra su asistencia. Márcalo, o déjalo sin marcar para que pueda registrarse.">
                                                    <i class="bi bi-hourglass me-1"></i>Sin registro
                                                </span>
                                            @else
                                                <span class="text-muted opacity-50 fw-bold">--</span>
                                            @endif
                                        </td>

                                        {{-- NOTA / COMENTARIO --}}
                                        <td class="text-center pe-4">
                                            {{-- Input oculto que guarda el texto de la nota para enviarlo con todo el form --}}
                                            <input type="hidden" id="input_comentario_{{ $alumno->id }}"
                                                name="asistencias[{{ $alumno->id }}][comentario]"
                                                value="{{ $comentario }}">

                                            <button type="button"
                                                class="btn btn-sm btn-link text-decoration-none btn-comentario p-0"
                                                data-alumno-id="{{ $alumno->id }}"
                                                data-nombre="{{ mb_convert_case(mb_strtolower($nombreCompleto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }}"
                                                data-comentario="{{ $comentario }}">
                                                <i id="icon_comentario_{{ $alumno->id }}"
                                                    class="bi {{ $comentario ? 'bi-chat-left-text-fill text-marca-green' : 'bi-chat-left-text text-secondary opacity-50' }} fs-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- BARRA INFERIOR DE GUARDADO (Sticky) --}}
                @if ($alumnos->count() > 0)
                    <div class="sticky-footer-bar d-flex justify-content-between align-items-center">
                        <div class="small text-muted d-none d-md-block">
                            <i class="bi bi-info-circle text-marca-green me-1"></i> Quien quede sin marcar se queda sin
                            registro.
                        </div>
                        <button type="submit"
                            class="btn btn-marca-green rounded-pill px-5 py-2 fw-bold shadow-sm fs-5 d-flex align-items-center">
                            <i class="bi bi-save2 me-2"></i> Guardar Asistencia
                        </button>
                    </div>
                @endif

            </div>
        </form>
    </div>
@endsection

@push('scripts')
    {{-- SweetAlert ya lo carga el layout --}}
    <script>
        // ¿Hay cambios sin guardar? Para avisar antes de salir de la página
        let listaModificada = false;

        // Mientras dure la clase, quien quede sin marcar todavía puede registrarse
        const claseEnCurso = @json($claseEnCurso);

        // Filas que todavía no tienen ningún estado marcado
        function filasSinMarcar() {
            return Array.from(document.querySelectorAll('.fila-alumno')).filter(function(fila) {
                return !fila.querySelector('.btn-check:checked');
            });
        }

        // Cuántos van en cada estado, en el resumen de arriba de la lista
        function actualizarResumen() {
            ['presente', 'falta', 'justificado'].forEach(function(estado) {
                const cuenta = document.querySelector('[data-cuenta="' + estado + '"]');
                if (cuenta) {
                    cuenta.textContent = document.querySelectorAll('.btn-check[value="' + estado + '"]:checked').length;
                }
            });

            const sinMarcar = filasSinMarcar().length;
            document.querySelectorAll('[data-cuenta="sin_marcar"], [data-cuenta="sin_marcar_boton"]').forEach(function(cuenta) {
                cuenta.textContent = sinMarcar;
            });
            ['cuenta-sin-marcar', 'boton-falta-sin-marcar'].forEach(function(id) {
                const elemento = document.getElementById(id);
                if (elemento) elemento.classList.toggle('d-none', sinMarcar === 0);
            });
        }

        // Función para marcar todos como presentes
        function marcarTodosPresentes() {
            const radiosPresente = document.querySelectorAll('.radio-presente');
            radiosPresente.forEach(radio => {
                radio.checked = true;
            });
            listaModificada = true;
            actualizarResumen();
        }

        // Al terminar la clase: los que siguen sin marcar quedan como falta (no toca
        // a los que el profesor ya marcó a mano)
        function marcarSinMarcarComoFalta() {
            filasSinMarcar().forEach(function(fila) {
                fila.querySelector('.btn-check[value="falta"]').checked = true;
            });
            listaModificada = true;
            actualizarResumen();
        }

        document.addEventListener('DOMContentLoaded', function() {
            actualizarResumen();

            document.querySelectorAll('#formAsistencia .btn-check').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    listaModificada = true;
                    actualizarResumen();
                });
            });

            const formulario = document.getElementById('formAsistencia');
            const enviar = function() {
                listaModificada = false;   // guardar no cuenta como "salir sin guardar"
                formulario.submit();
            };

            // Si alguien quedó sin marcar, se pregunta qué hacer con ellos
            formulario.addEventListener('submit', function(evento) {
                const sinMarcar = filasSinMarcar().length;
                if (sinMarcar === 0) {
                    listaModificada = false;
                    return;
                }

                evento.preventDefault();
                Swal.fire({
                    title: sinMarcar === 1 ? 'Hay 1 alumno sin marcar' : 'Hay ' + sinMarcar + ' alumnos sin marcar',
                    text: claseEnCurso ?
                        'Si guardas así, se quedan sin registro: todavía pueden registrarse mientras dure la clase; si no lo hacen, contará como falta.' :
                        'Si guardas así, se quedan sin registro y cuentan como falta.',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Marcarlos como falta y guardar',
                    denyButtonText: 'Guardar así',
                    cancelButtonText: 'Revisar la lista',
                    confirmButtonColor: '#dc3545',
                    denyButtonColor: '#009B4D',
                    cancelButtonColor: '#6c757d',
                    customClass: {
                        popup: 'rounded-4 shadow'
                    }
                }).then(function(resultado) {
                    if (resultado.isConfirmed) {
                        marcarSinMarcarComoFalta();
                        enviar();
                    } else if (resultado.isDenied) {
                        enviar();
                    }
                });
            });

            window.addEventListener('beforeunload', function(evento) {
                if (listaModificada) {
                    evento.preventDefault();
                    evento.returnValue = '';
                }
            });
        });

        // Lógica de SweetAlert para inyectar los comentarios en los inputs ocultos
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-comentario').forEach(btn => {
                btn.addEventListener('click', function() {
                    let alumnoId = this.dataset.alumnoId;
                    let nombre = this.dataset.nombre;
                    // Leemos el valor del input oculto por si el profe ya había escrito algo pero no ha guardado
                    let comentarioActual = document.getElementById('input_comentario_' + alumnoId)
                        .value;

                    Swal.fire({
                        title: 'Observaciones',
                        text: nombre,
                        input: 'textarea',
                        inputValue: comentarioActual,
                        inputPlaceholder: 'Escribe una observación...',
                        showCancelButton: true,
                        confirmButtonColor: '#009B4D',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Confirmar Nota',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            popup: 'rounded-4 shadow',
                            confirmButton: 'rounded-pill px-4',
                            cancelButton: 'rounded-pill px-4'
                        }
                    }).then((result) => {
                        // Si el profe dio en guardar, actualizamos el input oculto y el color del ícono
                        if (result.isConfirmed) {
                            let inputOculto = document.getElementById('input_comentario_' +
                                alumnoId);
                            let icono = document.getElementById('icon_comentario_' +
                                alumnoId);

                            inputOculto.value = result.value;
                            this.dataset.comentario = result
                                .value; // Actualizamos el data-comentario
                            listaModificada = true;

                            if (result.value.trim() !== '') {
                                icono.className =
                                    'bi bi-chat-left-text-fill text-marca-green fs-4';
                            } else {
                                icono.className =
                                    'bi bi-chat-left-text text-secondary opacity-50 fs-4';
                            }
                        }
                    });
                });
            });
        });
    </script>
@endpush
