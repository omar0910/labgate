@extends('layouts.admin')
@section('title', 'Crear Horario')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        /* Ajustes visuales para Select2 y inputs */
        .form-control,
        .form-select,
        .select2-container--bootstrap-5 .select2-selection {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 0.5rem !important;
            padding: 0.4rem 0.75rem;
        }

        .form-control:focus,
        .form-select:focus,
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #009B4D !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 155, 77, 0.15) !important;
            background-color: #ffffff !important;
        }

        /* Personalización de los Radio Buttons */
        .form-check-input:checked {
            background-color: #009B4D;
            border-color: #009B4D;
        }
    </style>
@endpush

@section('content')

    {{-- ENCABEZADO MODERNO --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-plus text-marca-green me-2"></i> Añadir Clase al Horario
            </h3>
            <p class="text-muted small mb-0 mt-1">Asigna materias, profesores y grupos a los laboratorios disponibles.</p>
        </div>
        <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Volver al calendario
        </a>
    </div>

    @php
        // Valores a dejar preseleccionados: lo que se acaba de escribir si hubo un error
        // de validación (old), o si no, lo del último horario guardado, para encadenar
        // varias altas del mismo laboratorio, día y grupo.
        $ultimo = session('ultimo_horario', []);
        $selCentro = old('centro_computo_id', $ultimo['centro_computo_id'] ?? null);
        $selDia = old('dia_semana', $ultimo['dia_semana'] ?? null);
        $selGrupo = old('grupo_id', $ultimo['grupo_id'] ?? null);
    @endphp

    {{-- CONFIRMACIÓN DEL HORARIO ANTERIOR --}}
    {{-- Al guardar se regresa a este mismo formulario, así que el aviso se muestra
         aquí para saber que el horario sí quedó registrado. --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- BLOQUE PARA VER ERRORES --}}
    @if ($errors->any())
        <div class="alert alert-danger shadow-sm rounded-4 mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Por favor, corrige los siguientes
                errores:</h6>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- TARJETA DEL FORMULARIO --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">

            <form action="{{ route('horarios.store') }}" method="POST" id="form-horario">
                @csrf

                {{-- 1. SECCIÓN: TIPO DE RESERVA Y LUGAR --}}
                <div class="mb-4 pb-3 border-bottom">
                    <h6 class="fw-bold text-marca-green text-uppercase mb-3">
                        <i class="bi bi-bookmark-check me-2"></i>Tipo de Reserva y Laboratorio
                    </h6>

                    <div class="row g-4 align-items-center">
                        {{-- Tipo de Reserva (Radios) --}}
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input shadow-sm" type="radio" name="tipo_reserva"
                                        id="tipo_recurrente" value="recurrente" checked>
                                    <label class="form-check-label fw-bold text-dark" for="tipo_recurrente">
                                        Clase Regular <span class="d-block fw-normal text-muted small">Todo el
                                            semestre</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input shadow-sm" type="radio" name="tipo_reserva"
                                        id="tipo_especial" value="especial">
                                    <label class="form-check-label fw-bold text-dark" for="tipo_especial">
                                        Reserva Única <span class="d-block fw-normal text-muted small">Un solo día
                                            específico</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Laboratorio --}}
                        <div class="col-md-6">
                            <label for="centro_computo_id" class="form-label fw-bold">Laboratorio asignado <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="bi bi-pc-display text-muted"></i></span>
                                <select name="centro_computo_id" class="form-select border-start-0 ps-0" required>
                                    @foreach ($centros as $centro)
                                        <option value="{{ $centro->id }}"
                                            {{ (string) $selCentro === (string) $centro->id ? 'selected' : '' }}>
                                            {{ $centro->nombre_centro }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. SECCIÓN: FECHA Y HORA --}}
                <div class="mb-4 pb-3 border-bottom">
                    <h6 class="fw-bold text-marca-green text-uppercase mb-3">
                        <i class="bi bi-clock-history me-2"></i>Fecha y Hora
                    </h6>

                    <div class="row g-4">
                        {{-- DÍA DE LA SEMANA (Visible si es Recurrente) --}}
                        <div class="col-md-4" id="div-dia-semana">
                            <label for="dia_semana" class="form-label fw-bold">Día de la Semana <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="bi bi-calendar-day text-muted"></i></span>
                                <select name="dia_semana" class="form-select border-start-0 ps-0">
                                    @foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] as $dia)
                                        <option value="{{ $dia }}" {{ $selDia === $dia ? 'selected' : '' }}>
                                            {{ $dia }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- FECHA ESPECÍFICA (Visible si es Especial) --}}
                        <div class="col-md-4" id="div-fecha-especial" style="display: none;">
                            <label for="fecha_especial" class="form-label fw-bold">Fecha de la Clase Especial <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="bi bi-calendar-date text-muted"></i></span>
                                <input type="date" name="fecha_especial" class="form-control border-start-0 ps-0"
                                    min="{{ date('Y-m-d') }}">
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">Selecciona el día exacto de la
                                reserva.</small>
                        </div>

                        {{-- Hora Inicio --}}
                        <div class="col-md-4">
                            <label for="hora_inicio" class="form-label fw-bold">Hora de Inicio <span
                                    class="text-danger">*</span></label>
                            <select name="hora_inicio" class="form-select select-search" required
                                data-placeholder="Seleccionar Inicio">
                                <option value=""></option>
                                @for ($i = 7; $i <= 21; $i++)
                                    @php $hora = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00'; @endphp
                                    <option value="{{ $hora }}">{{ $hora }}</option>
                                @endfor
                            </select>
                        </div>

                        {{-- Hora Fin --}}
                        <div class="col-md-4">
                            <label for="hora_fin" class="form-label fw-bold">Hora de Fin <span
                                    class="text-danger">*</span></label>
                            <select name="hora_fin" class="form-select select-search" required
                                data-placeholder="Seleccionar Fin">
                                <option value=""></option>
                                @for ($i = 8; $i <= 22; $i++)
                                    @php $hora = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00'; @endphp
                                    <option value="{{ $hora }}">{{ $hora }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                {{-- 3. SECCIÓN: DETALLES DE LA CLASE --}}
                <div class="mb-4">
                    <h6 class="fw-bold text-marca-green text-uppercase mb-3">
                        <i class="bi bi-journal-text me-2"></i>Detalles Académicos
                    </h6>

                    <div class="row g-4">
                        {{-- Materia --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Materia <span class="text-danger">*</span></label>
                            <select name="materia_id" class="form-select select-search" required
                                data-placeholder="Buscar o teclear materia...">
                                <option value=""></option>
                                @foreach ($materias as $materia)
                                    <option value="{{ $materia->id }}"
                                        {{ (string) old('materia_id') === (string) $materia->id ? 'selected' : '' }}>
                                        {{ $materia->nombre_materia }} @if ($materia->creditos)
                                            ({{ $materia->creditos }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Profesor (Nombre Completo) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Profesor asignado <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select select-search" required
                                data-placeholder="Buscar o teclear profesor...">
                                <option value=""></option>
                                @foreach ($profesores as $profe)
                                    {{-- Concatenamos los apellidos al nombre --}}
                                    <option value="{{ $profe->id }}"
                                        {{ (string) old('user_id') === (string) $profe->id ? 'selected' : '' }}>
                                        {{ trim($profe->name . ' ' . $profe->apellido_paterno . ' ' . $profe->apellido_materno) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Grupo --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Grupo / Semestre <span class="text-danger">*</span></label>
                            <select name="grupo_id" class="form-select select-search" required
                                data-placeholder="Buscar o teclear grupo...">
                                <option value=""></option>
                                @foreach ($grupos as $grupo)
                                    <option value="{{ $grupo->id }}"
                                        {{ (string) $selGrupo === (string) $grupo->id ? 'selected' : '' }}>
                                        {{ $grupo->nombre_grupo }}
                                        ({{ $grupo->semestre->nombre }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- COMENTARIOS --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">Comentarios / Observaciones Adicionales <span
                            class="text-muted fw-normal">(Opcional)</span></label>
                    <textarea name="comentario" class="form-control" rows="2"
                        placeholder="Ej: Requiere proyector, instalación de software específico..."></textarea>
                </div>

                {{-- BOTONES DE ACCIÓN --}}
                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    {{--<a href="{{ route('horarios.index') }}"
                        class="btn btn-outline-secondary rounded-pill fw-bold px-4">Cancelar</a>--}}
                    <button type="submit"
                        class="btn bg-marca-green text-white rounded-pill shadow-sm px-5 py-2 fw-bold fs-5">
                        <i class="bi bi-save me-2"></i> Guardar Horario
                    </button>
                </div>

            </form>
        </div>
    </div>

@endsection

@push('scripts')
    {{-- Cargamos jQuery y Select2 JS --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {

            // 1. INICIALIZAR SELECT2
            $('.select-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                language: {
                    noResults: function() {
                        return "No se encontraron resultados";
                    }
                }
            });

            // 2. AUTO-FOCUS AL ABRIR EL SELECT2
            // Esto permite que el usuario empiece a escribir inmediatamente sin tener que hacer clic en la caja de búsqueda interna.
            $(document).on('select2:open', () => {
                document.querySelector('.select2-search__field').focus();
            });

            // 3. LÓGICA DE MOSTRAR/OCULTAR FECHAS
            const radioRecurrente = document.getElementById('tipo_recurrente');
            const radioEspecial = document.getElementById('tipo_especial');
            const divDia = document.getElementById('div-dia-semana');
            const divFecha = document.getElementById('div-fecha-especial');

            function toggleTipo() {
                if (radioEspecial.checked) {
                    divDia.style.display = 'none';
                    divFecha.style.display = 'block';
                    // Hacer requerido el campo de fecha si está visible
                    divFecha.querySelector('input').setAttribute('required', 'required');
                } else {
                    divDia.style.display = 'block';
                    divFecha.style.display = 'none';
                    // Quitar el requerido si está oculto para que no bloquee el formulario
                    divFecha.querySelector('input').removeAttribute('required');
                }
            }

            if (radioRecurrente && radioEspecial) {
                radioRecurrente.addEventListener('change', toggleTipo);
                radioEspecial.addEventListener('change', toggleTipo);
                toggleTipo(); // Ejecutar al cargar
            }
        });
    </script>
@endpush
