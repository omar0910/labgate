@extends('layouts.admin')
@section('title', 'Horarios')

@section('content')

    {{-- 1. ENCABEZADO MODERNO --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-week text-marca-green me-2"></i> Horarios de Laboratorio
            </h3>
            <p class="text-muted small mb-0 mt-1">Consulta y gestiona las asignaciones de clases y uso libre.</p>
        </div>
    </div>

    {{-- 2. BARRA DE HERRAMIENTAS Y NAVEGACIÓN (Estilo Tarjeta Blanca) --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
        <div class="card-body p-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">

            {{-- IZQUIERDA: Selector de Laboratorio --}}
            <form action="{{ route('horarios.index') }}" method="GET" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="fecha" value="{{ $fechaActual }}">
                <label for="centro_id" class="form-label fw-bold text-nowrap mb-0 text-secondary small text-uppercase">
                    <i class="bi bi-pc-display me-1"></i> Laboratorio:
                </label>
                <select name="centro_id" id="centro_id"
                    class="form-select bg-light rounded-pill border-0 shadow-none fw-bold text-dark px-3 py-2"
                    style="width: auto; min-width: 220px;" onchange="this.form.submit()">
                    @foreach ($centros as $centro)
                        <option value="{{ $centro->id }}" {{ $centro->id == $centroSeleccionadoId ? 'selected' : '' }}>
                            {{ $centro->nombre_centro }}
                        </option>
                    @endforeach
                </select>
            </form>

            {{-- CENTRO: Navegación de Semanas + CALENDARIO DE SALTO RÁPIDO --}}
            <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border border-light shadow-sm">
                {{-- Botón Anterior --}}
                <a href="{{ route('horarios.index', ['fecha' => $inicioSemana->copy()->subWeek()->format('Y-m-d'), 'centro_id' => $centroSeleccionadoId]) }}"
                    class="btn btn-sm btn-light rounded-circle text-dark fw-bold" title="Semana Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>

                {{-- Texto de la Semana --}}
                <div class="mx-3 text-center lh-1" style="min-width: 140px;">
                    {{-- mb-1: con el interlineado tan cerrado (lh-1) la etiqueta se encimaba con la fecha --}}
                    <small class="text-muted d-block fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">SEMANA
                        DEL</small>
                    <span class="fw-bold text-marca-green text-nowrap fs-6">
                        {{ $inicioSemana->format('d/m') }} al {{ $finSemana->format('d/m/Y') }}
                    </span>
                </div>

                {{-- Botón Siguiente --}}
                <a href="{{ route('horarios.index', ['fecha' => $inicioSemana->copy()->addWeek()->format('Y-m-d'), 'centro_id' => $centroSeleccionadoId]) }}"
                    class="btn btn-sm btn-light rounded-circle text-dark fw-bold" title="Semana Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>

                <div class="border-start mx-2 h-100" style="height: 20px;"></div>

                {{-- EL BUSCADOR POR FECHA --}}
                <form action="{{ route('horarios.index') }}" method="GET" class="d-flex align-items-center m-0">
                    <input type="hidden" name="centro_id" value="{{ $centroSeleccionadoId }}">
                    <div class="input-group input-group-sm bg-white rounded-pill overflow-hidden border"
                        style="width: 140px;">
                        <span class="input-group-text bg-transparent border-0 text-secondary pe-1">
                            <i class="bi bi-calendar-event"></i>
                        </span>
                        <input type="date" name="fecha" class="form-control border-0 shadow-none bg-transparent ps-1"
                            style="font-size: 0.8rem; cursor: pointer;" value="{{ $fechaActual }}"
                            onchange="this.form.submit()" title="Ir a fecha">
                    </div>
                </form>

                {{-- Botón Hoy --}}
                <div class="ms-2 border-start ps-2">
                    <a href="{{ route('horarios.index', ['centro_id' => $centroSeleccionadoId]) }}"
                        class="btn btn-sm btn-outline-secondary rounded-pill fw-bold py-1 px-3" style="font-size: 0.75rem;">
                        Hoy
                    </a>
                </div>
            </div>

            {{-- DERECHA: Acciones --}}
            {{-- DERECHA: Acciones --}}
            <div class="d-flex gap-2 ms-auto">
                {{-- NUEVO BOTÓN PDF --}}
                <button type="button"
                    class="btn btn-outline-danger rounded-pill shadow-sm px-3 fw-bold d-flex align-items-center"
                    data-bs-toggle="modal" data-bs-target="#exportPdfModal">
                    <i class="bi bi-file-earmark-pdf-fill me-md-2"></i> <span class="d-none d-md-inline">Exportar PDF</span>
                </button>

                {{-- IMPORTAR LA REJILLA DE HORARIOS DEL PLANTEL --}}
                <button type="button"
                    class="btn btn-outline-success rounded-pill shadow-sm px-3 fw-bold d-flex align-items-center"
                    data-bs-toggle="modal" data-bs-target="#importarHorariosModal">
                    <i class="bi bi-file-earmark-arrow-up me-md-2"></i> <span class="d-none d-md-inline">Importar
                        horarios</span>
                </button>

                <a href="{{ route('horarios.papelera') }}"
                    class="btn btn-outline-secondary rounded-pill d-flex align-items-center px-3 fw-bold">
                    <i class="bi bi-trash me-md-2"></i> <span class="d-none d-md-inline">Papelera</span>
                </a>
                <a href="{{ route('horarios.create') }}"
                    class="btn btn-marca-black rounded-pill shadow-sm d-flex align-items-center px-3 fw-bold">
                    <i class="bi bi-plus-lg me-md-2"></i> <span class="d-none d-md-inline">Añadir Clase</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Mensajes --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- TABLA HORARIOS (ESTRUCTURA INTACTA, SÓLO ENVOLTURA ESTILIZADA) --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered text-center mb-0 tabla-rigida">
                    <thead>
                        <tr>
                            <th class="col-hora">HORARIO</th>
                            @foreach ($diasSemana as $dia)
                                <th>{{ strtoupper($dia) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($horasDisponibles as $hora)
                            @php $horaSiguiente = date('H:i', strtotime($hora . ' +1 hour')); @endphp
                            <tr>
                                {{-- Columna Hora --}}
                                <td class="celda-hora align-middle p-0">
                                    <div class="bloque-hora">
                                        {{ date('H:i', strtotime($hora)) }} - {{ $horaSiguiente }}
                                    </div>
                                </td>

                                {{-- Columnas Días --}}
                                @foreach ($diasSemana as $dia)
                                    @php $celdaData = $mapaHorarios[$dia][$hora] ?? null; @endphp

                                    @if ($celdaData)
                                        @php
                                            $horario = $celdaData['info'];
                                            $colorIndex = $horario->id % 5;
                                        @endphp

                                        <td class="celda-clase p-1 position-relative color-{{ $colorIndex }}">
                                            @if ($celdaData['es_especial'])
                                                <span class="etiqueta-eventual"><i
                                                        class="bi bi-star-fill me-1"></i>Único</span>
                                            @endif

                                            <div
                                                class="contenido-clase h-100 d-flex flex-column justify-content-center pt-2">
                                                {{-- 1. PROFESOR: Primera letra del nombre + Primer apellido --}}
                                                @php
                                                    $nombreProfesor = $horario->user->name ?? '';
                                                    $apellidoPaterno = $horario->user->apellido_paterno ?? '';

                                                    // Obtenemos la primera letra del nombre si existe
                                                    $inicialNombre = $nombreProfesor
                                                        ? substr($nombreProfesor, 0, 1) . '.'
                                                        : '';

                                                    // Construimos el string final
                                                    $profesorFormateado = trim($inicialNombre . $apellidoPaterno);
                                                @endphp
                                                <div class="profesor">{{ $profesorFormateado ?: 'Sin Asignar' }}</div>

                                                {{-- 2. MATERIA --}}
                                                <div class="materia">{{ $horario->materia->nombre_materia }}</div>

                                                {{-- 3. GRUPO --}}
                                                <div class="grupo">{{ $horario->grupo->nombre_grupo }}</div>
                                            </div>

                                            @if ($celdaData['es_inicio'])
                                                <div class="acciones-flotantes shadow-sm">
                                                    @if ($horario->comentario)
                                                        <button type="button"
                                                            class="btn-ver-comentario border-0 bg-transparent text-marca-green me-1"
                                                            data-comentario="{{ $horario->comentario }}"
                                                            title="Ver Observaciones">
                                                            <i class="bi bi-chat-text-fill"></i>
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('horarios.edit', $horario->id) }}"
                                                        class="text-warning me-1" title="Editar">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>
                                                    <form action="{{ route('horarios.destroy', $horario->id) }}"
                                                        method="POST" class="d-inline form-eliminar">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="border-0 bg-transparent text-danger"
                                                            title="Eliminar">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    @else
                                        <td class="celda-vacia"></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- MODAL PARA EXPORTAR PDF --}}
    <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom border-2 border-marca-green">
                    <h5 class="modal-title fw-bold text-dark" id="exportPdfModalLabel">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> Exportar Horarios
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <form action="{{ route('horarios.exportPdf') }}" method="POST">
                    @csrf
                    {{-- Mandamos la fecha actual para que imprima la semana correcta --}}
                    <input type="hidden" name="fecha_actual" value="{{ $fechaActual }}">

                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Selecciona los laboratorios que deseas incluir en el documento
                            PDF. Se generará una página por cada laboratorio seleccionado.</p>

                        <div class="mb-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input focus-ring-marca-green" type="checkbox" id="checkAllLabs"
                                    onchange="toggleAllLabs(this)">
                                <label class="form-check-label fw-bold text-marca-green" for="checkAllLabs">Seleccionar
                                    todos</label>
                            </div>
                            <hr class="text-muted">
                            <div class="row" id="laboratoriosContainer">
                                @foreach ($centros as $centro)
                                    <div class="col-12 col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input lab-checkbox" type="checkbox"
                                                name="centros_ids[]" value="{{ $centro->id }}"
                                                id="centro_pdf_{{ $centro->id }}"
                                                {{ $centro->id == $centroSeleccionadoId ? 'checked' : '' }}>
                                            <label class="form-check-label text-dark" style="font-size: 0.9rem;"
                                                for="centro_pdf_{{ $centro->id }}">
                                                {{ $centro->nombre_centro }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4 border-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-marca-green rounded-pill px-4 fw-bold">
                            <i class="bi bi-download me-1"></i> Descargar PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: IMPORTAR LA REJILLA DE HORARIOS DEL PLANTEL --}}
    <div class="modal fade" id="importarHorariosModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-file-earmark-arrow-up text-marca-green me-2"></i> Importar horarios
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form action="{{ route('horarios.importar.revisar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body py-4">

                        <div class="alert bg-light border text-muted small rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="bi bi-info-circle-fill text-marca-green me-1"></i> Cómo funciona:
                            </h6>
                            <ul class="mb-0 ps-3 mt-2">
                                <li>Es el <strong>archivo de horarios de los laboratorios</strong>, el que trae una fila
                                    por cada hora del día.</li>
                                <li>Primero verás <strong>todo lo que pasaría</strong>: clases nuevas, las que cambian de
                                    hora y las que ya no aparecen. <strong>Nada se guarda hasta que confirmes.</strong>
                                </li>
                                <li>Las clases que cambian de horario <strong>se editan</strong>, no se borran: conservan
                                    las asistencias ya registradas.</li>
                                <li>Se registran en el semestre activo:
                                    <strong>{{ $semestreActivo->nombre ?? '' }}</strong>.
                                </li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <label for="archivo_horarios" class="form-label fw-bold">Selecciona tu archivo</label>
                            <input class="form-control bg-light" type="file" id="archivo_horarios" name="archivo"
                                accept=".xlsx, .xls, .csv" required>
                        </div>

                        <div class="small text-muted">
                            Columnas necesarias: <span class="badge bg-secondary">DIA</span>
                            <span class="badge bg-secondary">INICIO</span>
                            <span class="badge bg-secondary">TERMINO</span>
                            <span class="badge bg-secondary">MATERIA</span>
                            <span class="badge bg-secondary">GRUPO</span>, y
                            <span class="badge bg-secondary">AREA</span> (o el nombre de la hoja). La columna
                            <span class="badge bg-secondary">DOCENTE</span> se usa para asignar al profesor.
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill fw-bold px-4"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-marca-green rounded-pill fw-bold px-4">
                            <i class="bi bi-search me-1"></i> Revisar archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Pequeño script para el botón de "Seleccionar todos"
        function toggleAllLabs(source) {
            checkboxes = document.querySelectorAll('.lab-checkbox');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
@endsection

@push('styles')
    <style>
        /* TABLA MODERNA Y SUAVE */
        .tabla-rigida {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dee2e6;
            /* Borde más suave */
        }

        /* --- ENCABEZADOS --- */
        .tabla-rigida thead th {
            background-color: #009B4D !important;
            /* Verde Institucional */
            color: #ffffff;
            text-transform: uppercase;
            font-weight: 700;
            padding: 12px 10px;
            border: 1px solid #dee2e6;
            letter-spacing: 0.5px;
        }

        .tabla-rigida thead th.col-hora {
            background-color: #FFE900 !important;
            /* Amarillo Institucional */
            color: #1a1a1a;
            width: 120px;
            border: 1px solid #dee2e6;
        }

        /* ALTO FIJO */
        .bloque-hora {
            height: 95px;
            /* Un pelín más alto para que respiren los textos */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #495057;
            font-size: 0.9rem;
        }

        /* --- CELDAS BASE --- */
        .celda-hora {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .celda-vacia {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            height: 95px;
        }

        /* --- ESTILO DE CLASE --- */
        .celda-clase {
            background-color: #ffffff !important;
            border: 1px solid #dee2e6;
            vertical-align: middle;
            overflow: hidden;
            color: #333;
            transition: all 0.2s ease;
        }

        .celda-clase:hover {
            background-color: #f1f8f5 !important;
            /* Verde muy clarito al pasar el mouse */
        }

        /* --- COLORES LATERALES (Modernizados) --- */
        .color-0 {
            border-left: 6px solid #009B4D !important;
        }

        /* Verde principal */
        .color-1 {
            border-left: 6px solid #f59e0b !important;
        }

        /* Ámbar/Naranja oscuro */
        .color-2 {
            border-left: 6px solid #0ea5e9 !important;
        }

        /* Azul claro */
        .color-3 {
            border-left: 6px solid #8b5cf6 !important;
        }

        /* Morado */
        .color-4 {
            border-left: 6px solid #ec4899 !important;
        }

        /* Rosa oscuro */

        /* --- TEXTOS --- */
        .contenido-clase .materia {
            font-weight: 800;
            font-size: 0.85rem;
            line-height: 1.2;
            margin-bottom: 4px;
            text-transform: uppercase;
            color: #1a1a1a !important;
        }

        .contenido-clase .profesor {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
        }

        .contenido-clase .grupo {
            font-size: 0.7rem;
            color: #adb5bd;
            font-weight: 600;
        }

        /* BOTONES FLOTANTES MODERNIZADOS */
        .acciones-flotantes {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: #ffffff;
            border-radius: 50rem;
            /* Forma de píldora */
            padding: 4px 8px;
            display: none;
            z-index: 10;
            border: 1px solid #e9ecef;
        }

        .celda-clase:hover .acciones-flotantes {
            display: flex;
            align-items: center;
            gap: 5px;
            animation: fadeIn 0.2s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NUEVO ESTILO: La etiqueta de clase única */
        .etiqueta-eventual {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #1a1a1a;
            /* Negro para mayor contraste profesional */
            color: #FFE900;
            /* Texto amarillo */
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 8px;
            letter-spacing: 0.5px;
            border-bottom-left-radius: 0.5rem;
            z-index: 2;
            text-transform: uppercase;
        }

        .contenido-clase .materia {
            margin-top: 2px;
            padding-right: 5px;
            padding-left: 5px;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // 1. Lógica para eliminar (Estilizada)
            document.querySelectorAll('.form-eliminar').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: "Esta clase se moverá a la papelera.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, borrar',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            popup: 'rounded-4 shadow',
                            confirmButton: 'rounded-pill px-4',
                            cancelButton: 'rounded-pill px-4'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });

            // 2. Lógica para ver comentarios (Estilizada)
            document.querySelectorAll('.btn-ver-comentario').forEach(boton => {
                boton.addEventListener('click', function() {
                    const mensaje = this.getAttribute('data-comentario');
                    Swal.fire({
                        title: 'Observaciones de la clase',
                        text: mensaje,
                        icon: 'info',
                        confirmButtonText: 'Cerrar',
                        confirmButtonColor: '#009B4D',
                        /* Verde Institucional */
                        customClass: {
                            popup: 'rounded-4 shadow',
                            confirmButton: 'rounded-pill px-5'
                        }
                    });
                });
            });

        });
    </script>
@endpush
