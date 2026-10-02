@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Pase de Lista')

@section('content')

    {{-- ESTILOS INSTITUCIONALES Y DE LA TABLA --}}
    <style>
        /* Casilla de "equipo personal" en el verde institucional */
        .interruptor-personal:checked {
            background-color: var(--marca-green);
            border-color: var(--marca-green);
        }

        /* Estilos para los botones Toggle de Asistencia */
        .btn-check:checked+.btn-outline-success {
            background-color: var(--marca-green);
            border-color: var(--marca-green);
            color: white;
        }

        .btn-check:checked+.btn-outline-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-check:checked+.btn-outline-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: black;
        }

        /* Inputs suaves de la tabla */
        .form-control-tabla {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 0.3rem 0.6rem;
            transition: all 0.2s;
        }

        .form-control-tabla:focus {
            background-color: #ffffff;
            border-color: var(--marca-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 155, 77, 0.15);
            outline: none;
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

        {{-- ENCABEZADO DE LA FECHA Y CLASE --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-card-checklist text-marca-green me-2"></i> Registro de
                    Asistencia</h3>
                <p class="text-muted small mb-1 mt-1">
                    <span class="fw-bold">{{ $horario->materia->nombre_materia }}</span> •
                    <span class="text-marca-green fw-bold">{{ ucfirst(\Carbon\Carbon::parse($fecha)->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
                </p>

                {{-- NUEVO: Información del Profesor y Grupo --}}
                @php
                    $nombreProf = $horario->user->name ?? '';
                    $apePaterno = $horario->user->apellido_paterno ?? '';
                    $apeMaterno = $horario->user->apellido_materno ?? '';

                    // Unimos todo el nombre
                    $nombreCompletoProf = trim($nombreProf . ' ' . $apePaterno . ' ' . $apeMaterno);

                    // Formateamos para que se vea elegante (Ej: Juan Perez Lopez)
                    $profesorFinal = mb_convert_case(
                        mb_strtolower($nombreCompletoProf, 'UTF-8'),
                        MB_CASE_TITLE,
                        'UTF-8',
                    );
                @endphp
                <p class="text-muted small mb-0">
                    <span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i> Prof.
                        {{ $profesorFinal ?: 'Sin Asignar' }}</span>
                    <span class="badge bg-light text-dark border ms-1"><i class="bi bi-people me-1"></i> Grupo:
                        {{ $horario->grupo->nombre_grupo }}</span>
                    {{-- A qué sesión se anota la lista (útil en reposiciones) --}}
                    <span class="badge bg-light text-dark border ms-1"><i class="bi bi-clock me-1"></i> Sesión:
                        {{ $horario->etiquetaDeSesion() }}{{ $horario->centroComputo ? ' · ' . $horario->centroComputo->nombre_centro : '' }}</span>
                </p>
            </div>
            <div class="mt-3 mt-md-0">
                {{-- Si se llegó desde "Clases pendientes", se regresa ahí (con su propio origen);
                     si se llegó desde la lista de Gestión de Clases / Clases de hoy ("Corregir lista"), a esa lista --}}
                @php
                    $urlVolver = match (request('volver')) {
                        'pendientes' => route('admin.bitacora.pendientes', \App\Support\Origen::parametros(request())),
                        'lista' => route(auth()->user()->rol === 'Administrador' ? 'admin.ver-asistencia' : 'encargado.ver-asistencia', ['id' => $horario->id, 'fecha' => $fecha, 'centro_id' => request('centro_id', 'todos')]),
                        default => route('admin.bitacora.show', $horario->id),
                    };
                    $textoVolver = match (request('volver')) {
                        'pendientes' => 'Volver a Clases pendientes',
                        'lista' => 'Volver a la lista',
                        default => 'Volver a la clase',
                    };
                @endphp
                <a href="{{ $urlVolver }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> {{ $textoVolver }}
                </a>
            </div>
        </div>

        @include('partials.aviso-error')

        {{-- FORMULARIO PRINCIPAL --}}
        <form action="{{ route('admin.bitacora.store', ['id' => $horario->id, 'fecha' => $fecha]) }}" method="POST"
            id="formAsistencia">
            @csrf
            @if (request('volver') === 'pendientes')
                {{-- Captura de historial: al guardar se vuelve a pendientes y no se avisa por correo --}}
                <input type="hidden" name="volver" value="pendientes">
                @include('partials.origen-ocultos')
            @elseif (request('volver') === 'lista')
                {{-- Corrección desde la lista de consulta: al guardar se vuelve a ella --}}
                <input type="hidden" name="volver" value="lista">
                <input type="hidden" name="centro_id" value="{{ request('centro_id', 'todos') }}">
            @endif

            <div class="card shadow-sm border-0 rounded-4 bg-white mb-5">

                {{-- Barra de herramientas de la tabla --}}
                <div
                    class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Lista de Alumnos ({{ $alumnos->count() }})</h6>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold"
                        onclick="marcarTodosPresentes()">
                        <i class="bi bi-check-all me-1"></i> Marcar todos "Presente"
                    </button>
                </div>

                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 border-0" style="width: 5%;">#</th>
                                    <th class="border-0" style="width: 10%;">Matrícula</th>
                                    <th class="border-0" style="width: 20%;">Nombre del Alumno</th>
                                    <th class="border-0" style="width: 15%;">Carrera</th>
                                    <th class="text-center border-0" style="width: 20%;">Asistencia</th>
                                    <th class="text-center border-0" style="width: 12%;">No. PC</th>
                                    <th class="text-center pe-4 border-0" style="width: 18%;">Observaciones</th>
                                    {{-- Cambio a text-center --}}
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($alumnos as $index => $alumno)
                                    @php
                                        // Buscamos si ya existe un registro para este alumno en esta fecha
                                        $registro = $asistenciasGuardadas->get($alumno->id);

                                        // Si no hay registro, por defecto lo ponemos 'presente' para agilizar
                                        $estadoActual = $registro ? $registro->estado : 'presente';
                                        $pcActual = $registro ? $registro->numero_maquina : '';
                                        $personalActual = $registro ? (bool) $registro->equipo_personal : false;
                                        $comentarioActual = $registro ? $registro->comentario : '';

                                        // Formateo de nombre empezando por apellidos
                                        $apePaterno = $alumno->apellido_paterno ?? '';
                                        $apeMaterno = $alumno->apellido_materno ?? '';
                                        $nombreCompleto = trim($apePaterno . ' ' . $apeMaterno . ' ' . $alumno->name);
                                    @endphp
                                    <tr class="border-bottom border-light">
                                        <td class="ps-4 text-muted fw-bold">{{ $index + 1 }}</td>
                                        <td><span
                                                class="fw-bold text-dark font-monospace fs-6">{{ $alumno->matricula ?? 'S/M' }}</span>
                                        </td>
                                        <td class="fw-semibold text-dark text-capitalize">
                                            {{ mb_strtolower($nombreCompleto, 'UTF-8') }}
                                            @if (! $registro)
                                                {{-- Aún no se registra: la lista lo pone "presente" por omisión,
                                                     y sin esta marca no se distinguía de quien sí llegó --}}
                                                <div class="mt-1" style="text-transform: none;">
                                                    <span class="badge bg-light border text-muted fw-semibold"
                                                        title="Todavía no registra su asistencia. Si no vino, márcalo como falta.">
                                                        <i class="bi bi-hourglass me-1"></i>Sin registro
                                                    </span>
                                                </div>
                                            @endif
                                        </td>

                                        <td class="text-muted small">
                                            {{ $alumno->carrera ?? 'Sin asignar' }}
                                        </td>

                                        {{-- Toggles de Asistencia --}}
                                        <td class="text-center">
                                            <div class="btn-group shadow-sm" role="group">
                                                {{-- PRESENTE --}}
                                                <input type="radio" class="btn-check radio-presente"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="presente_{{ $alumno->id }}" value="presente"
                                                    {{ $estadoActual == 'presente' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-success btn-sm px-3"
                                                    for="presente_{{ $alumno->id }}" title="Presente">
                                                    <i class="bi bi-check-lg"></i>
                                                </label>

                                                {{-- FALTA --}}
                                                <input type="radio" class="btn-check"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="falta_{{ $alumno->id }}" value="falta"
                                                    {{ $estadoActual == 'falta' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-danger btn-sm px-3"
                                                    for="falta_{{ $alumno->id }}" title="Falta">
                                                    <i class="bi bi-x-lg"></i>
                                                </label>

                                                {{-- JUSTIFICADO --}}
                                                <input type="radio" class="btn-check"
                                                    name="asistencias[{{ $alumno->id }}][estado]"
                                                    id="justificado_{{ $alumno->id }}" value="justificado"
                                                    {{ $estadoActual == 'justificado' ? 'checked' : '' }}>
                                                <label class="btn btn-outline-warning btn-sm px-3"
                                                    for="justificado_{{ $alumno->id }}" title="Justificado">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                </label>
                                            </div>
                                        </td>

                                        {{-- NÚMERO DE PC (Editable) --}}
                                        <td class="text-center">
                                            <div class="input-group mx-auto shadow-sm" style="width: 105px;">
                                                <span class="input-group-text bg-light text-marca-green fw-bold px-2"
                                                    style="border: 1px solid #dee2e6; border-right: 0; border-radius: 0.5rem 0 0 0.5rem;">
                                                    PC-
                                                </span>
                                                <input type="number"
                                                    name="asistencias[{{ $alumno->id }}][numero_maquina]"
                                                    class="form-control form-control-tabla text-center fw-bold text-marca-green px-1 campo-pc"
                                                    style="border-left: 0; border-radius: 0 0.5rem 0.5rem 0;" min="1"
                                                    max="100" placeholder="{{ $personalActual ? 'Pers.' : '--' }}"
                                                    value="{{ $personalActual ? '' : $pcActual }}" @disabled($personalActual)>
                                            </div>
                                            {{-- Trabajó en su laptop: sin número de PC --}}
                                            <div class="form-check small mt-1 mb-0 d-inline-block">
                                                <input class="form-check-input interruptor-personal" type="checkbox"
                                                    name="asistencias[{{ $alumno->id }}][equipo_personal]" value="1"
                                                    id="personal_{{ $alumno->id }}" @checked($personalActual)>
                                                <label class="form-check-label text-muted" for="personal_{{ $alumno->id }}">
                                                    <i class="bi bi-laptop"></i> Personal
                                                </label>
                                            </div>
                                        </td>

                                        {{-- NUEVO ESTILO: Observaciones con SweetAlert --}}
                                        <td class="text-center pe-4">
                                            {{-- Input oculto que guarda el texto de la nota para enviarlo con todo el form --}}
                                            <input type="hidden" id="input_comentario_{{ $alumno->id }}"
                                                name="asistencias[{{ $alumno->id }}][comentario]"
                                                value="{{ $comentarioActual }}">

                                            <button type="button"
                                                class="btn btn-sm btn-link text-decoration-none btn-comentario p-0"
                                                data-alumno-id="{{ $alumno->id }}"
                                                data-nombre="{{ mb_convert_case(mb_strtolower($nombreCompleto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }}"
                                                data-comentario="{{ $comentarioActual }}">
                                                <i id="icon_comentario_{{ $alumno->id }}"
                                                    class="bi {{ $comentarioActual ? 'bi-chat-left-text-fill text-marca-green' : 'bi-chat-left-text text-secondary opacity-50' }} fs-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted border-0">
                                            <i class="bi bi-people fs-1 d-block mb-3 opacity-25"></i>
                                            <span class="d-block fw-bold text-dark fs-5">Sin alumnos inscritos</span>
                                            <small>No se encontraron alumnos en este grupo. Revisa el módulo de usuarios y
                                                grupos.</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- BARRA INFERIOR DE GUARDADO (Sticky) --}}
                @if ($alumnos->count() > 0)
                    <div class="sticky-footer-bar d-flex justify-content-between align-items-center">
                        <div class="small text-muted d-none d-md-block">
                            <i class="bi bi-info-circle text-marca-green me-1"></i> Asegúrate de asignar los números de PC
                            correctos.
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
    {{-- Importamos SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Equipo personal: sin número de PC (el campo se desactiva y no se envía)
        document.querySelectorAll('.interruptor-personal').forEach(function(interruptor) {
            interruptor.addEventListener('change', function() {
                const campo = this.closest('td').querySelector('.campo-pc');
                campo.disabled = this.checked;
                campo.value = '';
                campo.placeholder = this.checked ? 'Pers.' : '--';
            });
        });

        // Función mágica para marcar a todos como presentes con 1 clic
        function marcarTodosPresentes() {
            const radiosPresente = document.querySelectorAll('.radio-presente');
            radiosPresente.forEach(radio => {
                radio.checked = true;
            });
        }

        // Lógica de SweetAlert para inyectar los comentarios en los inputs ocultos
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-comentario').forEach(btn => {
                btn.addEventListener('click', function() {
                    let alumnoId = this.dataset.alumnoId;
                    let nombre = this.dataset.nombre;

                    // Leemos el valor del input oculto por si ya se había escrito algo
                    let comentarioActual = document.getElementById('input_comentario_' + alumnoId)
                        .value;

                    Swal.fire({
                        title: 'Observaciones',
                        text: nombre,
                        input: 'textarea',
                        inputValue: comentarioActual,
                        inputPlaceholder: 'Escribe una observación (Opcional)...',
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
                        // Si se confirma, actualizamos el input oculto y el color del ícono
                        if (result.isConfirmed) {
                            let inputOculto = document.getElementById('input_comentario_' +
                                alumnoId);
                            let icono = document.getElementById('icon_comentario_' +
                                alumnoId);

                            inputOculto.value = result.value;
                            this.dataset.comentario = result.value;

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
