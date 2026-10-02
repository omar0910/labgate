@extends('layouts.admin')
@section('title', 'Clases de Hoy')

@php
    // Se abre desde el Inicio (su tarjeta), la Semana o Reportes: "Volver" regresa
    // ahí y el menú marca esa sección. Sin origen, al Inicio.
    $regreso = \App\Support\Origen::de(request()) ?? [
        'url'   => route('admin.dashboard'),
        'texto' => 'Volver al Inicio',
        'menu'  => 'inicio',
    ];
@endphp

@section('menu', $regreso['menu'])

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
            transition: all .3s ease;
        }
    </style>

    <div class="container-fluid py-4">

        @php
            $esHoy = $fechaSeleccionada == \Carbon\Carbon::today()->format('Y-m-d');
        @endphp

        {{-- ENCABEZADO INSTITUCIONAL INTERACTIVO --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        {{-- bi-calendar-day dibuja la abreviatura "Fri" dentro del icono, en inglés --}}
                        <h4 class="fw-bold text-marca-black mb-1"><i class="bi bi-calendar-event text-marca-green me-2"></i>
                            Programación de Clases</h4>
                        <p class="text-muted small mb-0 mt-1">
                            Mostrando agenda del día:
                            <span
                                class="badge {{ $esHoy ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-dark' }} border fw-bold fs-6 ms-1">
                                {{ $diaNombre }}, {{ \Carbon\Carbon::parse($fechaSeleccionada)->format('d/m/Y') }}
                                @if ($esHoy)
                                    <i class="bi bi-calendar-check ms-1"></i> (Hoy)
                                @endif
                            </span>
                        </p>
                    </div>
                    <div class="col-md-7 mt-3 mt-md-0">

                        {{-- Regreso al panel, igual que en el resto de las tarjetas del inicio --}}
                        <div class="d-flex justify-content-md-end gap-2 mb-3">
                            {{-- Las clases de días anteriores que se quedaron sin capturar --}}
                            <a href="{{ route('admin.bitacora.pendientes', ['origen' => 'clases', 'dia' => $fechaSeleccionada, 'centro_id' => $centroSeleccionadoId]) }}"
                                class="btn bg-marca-green text-white rounded-pill shadow-sm px-4 fw-bold"
                                title="Clases pasadas sin la asistencia del profesor">
                                <i class="bi bi-clipboard-check me-1"></i> Clases pendientes
                            </a>
                            <a href="{{ $regreso['url'] }}"
                                class="btn btn-outline-secondary rounded-pill shadow-sm px-4" title="{{ $regreso['texto'] }}">
                                <i class="bi bi-arrow-left me-1"></i> {{ $regreso['texto'] }}
                            </a>
                        </div>

                        <form action="{{ route('admin.reportes.clases-hoy') }}" method="GET" id="filterForm"
                            class="row g-2 justify-content-md-end align-items-end">
                            {{-- Al cambiar de día o de laboratorio no se pierde a dónde regresa "Volver" --}}
                            @foreach (['origen', 'semestre_id', 'pestana'] as $campo)
                                @if (request()->filled($campo))
                                    <input type="hidden" name="{{ $campo }}" value="{{ request($campo) }}">
                                @endif
                            @endforeach

                            <div class="col-auto">
                                <label class="small fw-bold text-muted d-block mb-1">Laboratorio</label>
                                <select name="centro_id"
                                    class="form-select border border-secondary border-opacity-25 bg-light rounded-3 shadow-sm"
                                    style="min-width: 240px;" onchange="this.form.submit()">
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
                                <label class="small fw-bold text-muted d-block mb-1">Día de consulta</label>
                                <div class="input-group shadow-sm rounded-3">
                                    <input type="date" name="fecha" value="{{ $fechaSeleccionada }}"
                                        class="form-control border border-secondary border-opacity-25 bg-light"
                                        onchange="this.form.submit()">
                                    @if (!$esHoy)
                                        <a href="{{ route('admin.reportes.clases-hoy', ['centro_id' => $centroSeleccionadoId, 'fecha' => \Carbon\Carbon::today()->format('Y-m-d')]) }}"
                                            class="btn bg-marca-green text-white fw-bold px-3" title="Regresar a Hoy">
                                            Hoy
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @include('partials.aviso-error')

        @if ($clases->isEmpty())
            <div class="card shadow-sm border-0 rounded-4 mt-4">
                <div class="card-body text-center py-5">
                    @if ($fueraDePeriodo)
                        {{-- La fecha consultada no pertenece al semestre activo --}}
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-4 shadow-sm"
                            style="width: 100px; height: 100px;">
                            <i class="bi bi-calendar-x text-marca-yellow fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-marca-black">Fuera del periodo de clases</h4>
                        <p class="text-muted mx-auto" style="max-width: 460px;">
                            @if ($semestreActivo)
                                El semestre activo es
                                <strong class="text-marca-green">{{ $semestreActivo->nombre }}</strong>
                                ({{ \Carbon\Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') }}
                                al {{ \Carbon\Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') }}).
                                La fecha consultada queda fuera de ese rango.
                            @else
                                No hay ningún semestre marcado como activo. Puedes activarlo desde
                                <a href="{{ url('admin/semestres') }}" class="text-marca-green fw-bold">Periodos</a>.
                            @endif
                        </p>
                    @else
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-4 shadow-sm"
                            style="width: 100px; height: 100px;">
                            <i class="bi bi-calendar-x text-marca-green fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-marca-black">Día libre de clases</h4>
                        <p class="text-muted mx-auto" style="max-width: 400px;">
                            No hay ninguna clase programada en los laboratorios seleccionados para este día.
                        </p>
                    @endif
                </div>
            </div>
        @else
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase border-bottom">
                            <tr>
                                <th class="ps-4 border-0">Hora</th>
                                <th class="border-0">Laboratorio</th>
                                <th class="border-0">Materia / Grupo</th>
                                <th class="border-0">Profesor</th>
                                <th class="text-center border-0">Asistencia Docente</th>
                                <th class="text-center border-0">Observaciones</th>
                                <th class="text-center pe-4 border-0">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Por adelantado sólo se puede registrar falta o justificación
                                $esFutura = $fechaSeleccionada > \Carbon\Carbon::today()->format('Y-m-d');
                            @endphp
                            @foreach ($clases as $clase)
                                @php
                                    // Ya viene consultado desde el controlador (antes, una consulta por clase)
                                    $reporte = $reportes->get($clase->id);
                                    $registradosClase = (int) ($registrados[$clase->id] ?? 0);
                                    $estado = $reporte ? $reporte->estado : null;
                                    $observacion = $reporte ? $reporte->observaciones : '';
                                    $nombreCompleto = trim(
                                        ($clase->user->name ?? '') .
                                            ' ' .
                                            ($clase->user->apellido_paterno ?? '') .
                                            ' ' .
                                            ($clase->user->apellido_materno ?? ''),
                                    );
                                @endphp
                                <tr class="shadow-hover" id="clase-{{ $clase->id }}" data-estado="{{ $estado }}"
                                    data-registrados="{{ $registradosClase }}">
                                    <td class="ps-4 border-bottom border-light py-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm"
                                            style="background-color: #E5F5ED; color: #009B4D; border: 1px solid #009B4D; font-size: 0.85rem;">
                                            <i class="bi bi-clock-fill me-1"></i>
                                            {{ date('H:i', strtotime($clase->hora_inicio)) }} -
                                            {{ date('H:i', strtotime($clase->hora_fin)) }}
                                        </span>
                                    </td>

                                    <td class="border-bottom border-light">
                                        <span
                                            class="fw-bold text-marca-green">{{ $clase->centroComputo->nombre_centro }}</span>
                                    </td>

                                    <td class="border-bottom border-light">
                                        <div class="fw-bold text-dark">{{ $clase->materia->nombre_materia }}</div>
                                        <span
                                            class="badge bg-light text-dark border border-secondary border-opacity-25 rounded-pill small mt-1">
                                            <i class="bi bi-people-fill me-1"></i> {{ $clase->grupo->nombre_grupo }}
                                        </span>
                                    </td>

                                    <td class="border-bottom border-light">
                                        <div class="fw-bold text-dark text-capitalize" style="font-size: 0.9rem;">
                                            {{ mb_strtolower($nombreCompleto, 'UTF-8') }}
                                        </div>
                                    </td>

                                    <td class="text-center border-bottom border-light">
                                        <div
                                            class="btn-group shadow-sm rounded-pill p-1 bg-light border border-secondary border-opacity-10">
                                            <button onclick="confirmarCambio({{ $clase->id }}, 'asistio')"
                                                class="btn btn-sm rounded-circle border-0 {{ $estado == 'asistio' ? 'btn-success text-white' : 'btn-light text-muted' }}"
                                                style="width: 35px; height: 35px;"
                                                title="{{ $esFutura ? 'Esta clase todavía no llega' : 'Marcar Presente' }}"
                                                @disabled($esFutura)><i class="bi bi-check-lg"></i></button>
                                            <button onclick="confirmarCambio({{ $clase->id }}, 'falta')"
                                                class="btn btn-sm rounded-circle border-0 {{ $estado == 'falta' ? 'btn-danger text-white' : 'btn-light text-muted' }}"
                                                style="width: 35px; height: 35px;" title="Marcar Falta"><i
                                                    class="bi bi-x-lg"></i></button>
                                            <button onclick="confirmarCambio({{ $clase->id }}, 'justificado')"
                                                class="btn btn-sm rounded-circle border-0 {{ $estado == 'justificado' ? 'btn-warning text-dark' : 'btn-light text-muted' }}"
                                                style="width: 35px; height: 35px;" title="Marcar Justificado"><i
                                                    class="bi bi-file-text"></i></button>
                                        </div>
                                        <div class="mt-1 small fw-bold text-uppercase" style="font-size: 0.65rem;">
                                            @if ($estado == 'asistio')
                                                <span class="text-success">Presente</span>
                                            @elseif($estado == 'falta')
                                                <span class="text-danger">Falta</span>
                                            @elseif($estado == 'justificado')
                                                <span class="text-warning text-dark">Justificado</span>
                                            @else
                                                @include('partials.estado-sin-registro', ['horarioSinRegistro' => $clase, 'fechaSinRegistro' => $fechaSeleccionada])
                                            @endif
                                        </div>
                                    </td>

                                    {{-- NUEVA COLUMNA DE OBSERVACIONES --}}
                                    <td class="text-center border-bottom border-light">
                                        <input type="hidden" id="input_comentario_{{ $clase->id }}"
                                            value="{{ $observacion }}">
                                        <button type="button"
                                            class="btn btn-sm btn-link text-decoration-none btn-comentario p-0"
                                            data-clase-id="{{ $clase->id }}"
                                            data-nombre="{{ mb_convert_case(mb_strtolower($nombreCompleto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }}"
                                            data-comentario="{{ $observacion }}" title="Agregar Observación">
                                            <i id="icon_comentario_{{ $clase->id }}"
                                                class="bi {{ $observacion ? 'bi-chat-left-text-fill text-marca-green' : 'bi-chat-left-text text-secondary opacity-50' }} fs-5"></i>
                                        </button>
                                    </td>

                                    <td class="text-center pe-4 border-bottom border-light">
                                        {{-- Lleva el filtro de laboratorio para que "Volver" regrese a la misma agenda --}}
                                        <a href="{{ route('admin.ver-asistencia', ['id' => $clase->id, 'fecha' => $fechaSeleccionada, 'centro_id' => $centroSeleccionadoId]) }}"
                                            class="btn btn-sm rounded-pill fw-bold px-3 text-white shadow-sm bg-marca-green text-nowrap"
                                            title="{{ $registradosClase }} {{ $registradosClase == 1 ? 'alumno registrado' : 'alumnos registrados' }}">
                                            <i class="bi bi-people-fill me-1"></i> Lista
                                            <span class="badge bg-white text-marca-green ms-1">{{ $registradosClase }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Registrar si el profesor dio la clase (el mismo código que "Gestión de Clases" del encargado) --}}
    @include('partials.registro-profesor', ['urlClase' => url('admin/clase'), 'fechaSeleccionada' => $fechaSeleccionada])
@endsection
