{{--
    La lista de consulta de una clase en un día: "Lista" de Gestión de Clases
    (encargado) y de Clases de hoy (administrador). Las dos vistas eran copias.

    Recibe: $horario, $fecha, $lista (App\Support\ListaDelDia::de) y $regreso
    (['url', 'texto'] del botón "Volver").
--}}
@include('partials.estilos-progreso')

@php
    $nombreCompletoProfe = trim(($horario->user->name ?? '') . ' ' . ($horario->user->apellido_paterno ?? '') . ' ' . ($horario->user->apellido_materno ?? ''));
    $conteos = $lista['conteos'];
    $yaLlego = $fecha <= \Carbon\Carbon::today()->format('Y-m-d');
@endphp

<div class="container-fluid py-4">

    {{-- ENCABEZADO MODERNO INSTITUCIONAL --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-card-checklist text-marca-green me-2"></i> Reporte de
                Asistencia</h3>
            <p class="text-muted small mb-1 mt-1">
                <span class="fw-bold">{{ $horario->materia->nombre_materia ?? 'Clase' }}</span> •
                <span
                    class="text-marca-green fw-bold">{{ ucfirst(\Carbon\Carbon::parse($fecha)->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
            </p>

            <p class="text-muted small mb-0">
                <span class="badge bg-light text-dark border me-1"><i class="bi bi-clock"></i>
                    {{ date('H:i', strtotime($horario->hora_inicio)) }} -
                    {{ date('H:i', strtotime($horario->hora_fin)) }}</span>
                <span class="badge bg-light text-dark border me-1"><i class="bi bi-people me-1"></i> Grupo:
                    {{ $horario->grupo->nombre_grupo ?? 'Sin grupo' }}</span>
                <span class="badge bg-light text-dark border"><i class="bi bi-person-badge"></i> Prof.
                    {{ $nombreCompletoProfe }}</span>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            {{-- Para corregir la lista: la Bitácora (aquí es sólo de consulta) --}}
            @if ($yaLlego)
                <a href="{{ route('admin.bitacora.asistencia', ['id' => $horario->id, 'fecha' => $fecha]) }}?{{ http_build_query(['volver' => 'lista', 'centro_id' => request('centro_id', 'todos')]) }}"
                    class="btn btn-outline-marca-green rounded-pill shadow-sm px-4 fw-bold">
                    <i class="bi bi-pencil-square me-1"></i> Corregir lista
                </a>
            @endif
            <a href="{{ $regreso['url'] }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> {{ $regreso['texto'] }}
            </a>
        </div>
    </div>

    {{-- Al volver de corregirla en la Bitácora --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- Qué pasó con la clase ese día (por qué hay alumnos sin falta) --}}
    @switch($lista['situacion'])
        @case('en_curso')
            <div class="alert alert-light border rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-broadcast text-marca-green fs-5"></i>
                <div>La clase sigue en curso (termina a las {{ date('H:i', strtotime($horario->hora_fin)) }}): quien
                    todavía no se registra aún puede hacerlo.</div>
            </div>
        @break

        @case('futura')
            <div class="alert alert-light border rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-marca-green fs-5"></i>
                <div>Esta clase todavía no llega.</div>
            </div>
        @break

        @case('no_impartida')
            <div class="alert rounded-4 d-flex align-items-center gap-2 border-0 border-start border-4 border-warning"
                style="background-color: #fffbe0;">
                <i class="bi bi-calendar-x text-warning fs-5"></i>
                <div>El profesor está registrado como
                    <strong>{{ $lista['estadoProfesor'] === 'justificado' ? 'falta justificada' : 'falta' }}</strong>:
                    la clase no se dio, así que no cuenta como falta de los alumnos.</div>
            </div>
        @break

        @case('sin_lista')
            <div class="alert alert-light border rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-marca-green fs-5"></i>
                <div>Ese día nadie quedó registrado en el sistema: no se sabe quién asistió (quizá la lista fue en
                    papel). Puedes capturarla con <strong>Corregir lista</strong>.</div>
            </div>
        @break
    @endswitch

    {{-- Resumen Rápido Institucional --}}
    <div class="row mb-4 g-3">
        <div class="col-md-3 col-6">
            <div
                class="card bg-success bg-opacity-10 text-success border border-success border-opacity-25 shadow-sm rounded-4 h-100">
                <div class="card-body text-center p-3">
                    <h2 class="fw-black mb-0">{{ $conteos['presentes'] }}</h2>
                    <small class="fw-bold text-uppercase" style="font-size: 0.7rem;">Presentes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div
                class="card bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 shadow-sm rounded-4 h-100">
                <div class="card-body text-center p-3">
                    <h2 class="fw-black mb-0">{{ $conteos['justificados'] }}</h2>
                    <small class="fw-bold text-uppercase" style="font-size: 0.7rem;">Justificados</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div
                class="card bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 shadow-sm rounded-4 h-100">
                <div class="card-body text-center p-3">
                    <h2 class="fw-black mb-0">{{ $conteos['faltas'] }}</h2>
                    <small class="fw-bold text-uppercase" style="font-size: 0.7rem;">Faltas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div
                class="card bg-light text-secondary border border-secondary border-opacity-25 shadow-sm rounded-4 h-100">
                <div class="card-body text-center p-3">
                    <h2 class="fw-black mb-0">{{ $conteos['total'] }}</h2>
                    <small class="fw-bold text-uppercase" style="font-size: 0.7rem;">Total Lista</small>
                    @if ($conteos['pendientes'] > 0)
                        <small class="d-block" style="font-size: 0.7rem;">{{ $conteos['pendientes'] }} aún sin
                            registrarse</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- LISTA DE ALUMNOS (Solo Lectura - Auditoría) --}}
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-5">
        <div
            class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">Lista de Alumnos ({{ $conteos['total'] }})</h6>
        </div>

        <div class="card-body p-0 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 border-0" style="width: 5%;">#</th>
                            <th class="border-0" style="width: 10%;">Matrícula</th>
                            <th class="border-0" style="width: 30%;">Nombre del Alumno</th>
                            <th class="border-0 d-none d-lg-table-cell" style="width: 20%;">Carrera</th>
                            <th class="text-center border-0" style="width: 15%;">Estado</th>
                            <th class="text-center border-0 pe-4" style="width: 20%;">Asignación PC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lista['alumnos'] as $index => $fila)
                            @php
                                $alumno = $fila['alumno'];
                                $asistencia = $fila['registro'];
                                $estado = \App\Support\ListaDelDia::estado($fila['estado']);
                                $nombreCompleto = trim(($alumno->apellido_paterno ?? '') . ' ' . ($alumno->apellido_materno ?? '') . ' ' . $alumno->name);
                            @endphp

                            <tr class="border-bottom border-light">
                                <td class="ps-4 text-muted fw-bold">{{ $index + 1 }}</td>

                                <td>
                                    <span class="fw-bold text-dark font-monospace fs-6">{{ $alumno->matricula ?? 'S/M' }}</span>
                                </td>

                                <td class="fw-semibold text-dark text-capitalize">
                                    {{ mb_strtolower($nombreCompleto, 'UTF-8') }}
                                </td>

                                <td class="text-muted small d-none d-lg-table-cell">
                                    {{ $alumno->carrera ?? 'Sin asignar' }}
                                </td>

                                {{-- COLUMNA ESTADO (Auditoría) --}}
                                <td class="text-center">
                                    <span class="estado-clase {{ $estado['clase'] }}">
                                        <i class="bi {{ $estado['icono'] }}"></i> {{ $estado['texto'] }}
                                    </span>
                                </td>

                                {{-- NÚMERO DE PC Y HORA --}}
                                <td class="text-center pe-4">
                                    @if ($asistencia && $asistencia->estado == 'presente')
                                        @if ($asistencia->equipo_personal)
                                            {{-- Trabajó en su laptop: no ocupó PC del laboratorio --}}
                                            <span
                                                class="badge bg-light border border-marca-black text-marca-black fs-6 fw-bold shadow-sm px-3 py-2 d-block mb-1">
                                                <i class="bi bi-laptop me-1"></i>Personal
                                            </span>
                                        @elseif ($asistencia->numero_maquina)
                                            <span
                                                class="badge bg-marca-black border text-marca-yellow fs-6 fw-bold shadow-sm px-3 py-2 d-block mb-1">
                                                PC-{{ str_pad($asistencia->numero_maquina, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @else
                                            {{-- Antes salía "PC-00" cuando la lista se capturó sin número --}}
                                            <span class="text-muted small fw-bold d-block mb-1">Sin PC</span>
                                        @endif
                                        <small class="text-muted" style="font-size: 0.7rem;">
                                            <i class="bi bi-clock"></i>
                                            {{ \Carbon\Carbon::parse($asistencia->fecha_hora_registro)->format('H:i') }}
                                            hrs
                                        </small>
                                    @elseif($asistencia && $asistencia->estado == 'justificado')
                                        <span class="text-muted small fw-bold">N/A</span>
                                    @else
                                        <span class="text-muted opacity-50 fw-bold">--</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 opacity-25 d-block mb-3 text-marca-green"></i>
                                    No hay alumnos inscritos en este grupo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
