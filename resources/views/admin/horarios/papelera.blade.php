@extends('layouts.admin')
@section('title', 'Papelera de Reciclaje')

@section('content')

    {{-- ESTILOS PERSONALIZADOS DEL TEMA --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f8f9fa;
            transition: all .2s ease;
        }

        .form-check-input:checked {
            background-color: #009B4D;
            border-color: #009B4D;
        }

        .fila-seleccionada {
            background-color: rgba(0, 155, 77, .06) !important;
        }
    </style>

    {{-- 1. ENCABEZADO MODERNO --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-trash3 text-danger me-2"></i> Papelera de Horarios</h3>
            <p class="text-muted small mb-0 mt-1">Recupera las clases y reservas que fueron eliminadas por error.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> Volver al calendario
            </a>
        </div>
    </div>

    {{-- MENSAJES DE ALERTA --}}
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

    @if (session('fallos'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle-fill me-2"></i>
                {{ count(session('fallos')) }} {{ count(session('fallos')) == 1 ? 'no se pudo' : 'no se pudieron' }}:
            </div>
            <ul class="mb-0 small ps-4">
                @foreach (session('fallos') as $fallo)
                    <li>{{ $fallo }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm rounded-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- 2. FILTROS: semestre y laboratorio --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body py-3">
            <form action="{{ route('horarios.papelera') }}" method="GET"
                class="d-flex flex-wrap align-items-center gap-3 m-0">
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted text-uppercase" for="filtro-semestre">Semestre</label>
                    <select name="semestre" id="filtro-semestre" class="form-select form-select-sm rounded-pill"
                        onchange="this.form.submit()">
                        @foreach ($semestres as $semestre)
                            <option value="{{ $semestre->id }}"
                                {{ (string) $semestreId === (string) $semestre->id ? 'selected' : '' }}>
                                {{ $semestre->nombre }}{{ $semestre->es_activo ? ' (activo)' : '' }}
                            </option>
                        @endforeach
                        <option value="todos" {{ $semestreId === 'todos' ? 'selected' : '' }}>Todos los semestres
                        </option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted text-uppercase" for="filtro-centro">Laboratorio</label>
                    <select name="centro" id="filtro-centro" class="form-select form-select-sm rounded-pill"
                        onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach ($centros as $centro)
                            <option value="{{ $centro->id }}"
                                {{ (string) $centroId === (string) $centro->id ? 'selected' : '' }}>
                                {{ $centro->nombre_centro }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="ms-auto small text-muted">
                    {{ $horariosBorrados->count() }} de {{ $totalEnPapelera }} en la papelera
                </span>
            </form>
        </div>
    </div>

    {{-- 3. CONTENIDO (Vacío o Tabla) --}}
    @if ($horariosBorrados->isEmpty())
        {{-- Estado Vacío Moderno --}}
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-trash3 fs-1 text-muted opacity-25 d-block mb-3"></i>
                <h5 class="fw-bold text-dark">No hay horarios eliminados aquí</h5>
                @if ($totalEnPapelera > 0 && $semestreId !== 'todos')
                    <p class="text-muted small mb-3">
                        Hay {{ $totalEnPapelera }} en la papelera de otros semestres o laboratorios.
                    </p>
                    <a href="{{ route('horarios.papelera', ['semestre' => 'todos']) }}"
                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Ver todos</a>
                @else
                    <p class="text-muted small mb-0">No hay horarios eliminados recientemente en el sistema.</p>
                @endif
            </div>
        </div>
    @else
        {{-- Acciones sobre las marcadas. El formulario va aparte (las filas se ligan con form=) para
             no meter unos formularios dentro de otros. --}}
        <form action="{{ route('horarios.papelera.acciones') }}" method="POST" id="form-lote">
            @csrf
            <input type="hidden" name="accion" id="accion-lote">
        </form>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-muted me-2"><span id="contador-marcadas">0</span> marcadas</span>
            <button type="button" class="btn btn-marca-green btn-sm rounded-pill px-3 fw-bold boton-lote"
                data-accion="restaurar" disabled>
                <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar marcadas
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold boton-lote"
                data-accion="eliminar" disabled>
                <i class="bi bi-trash3 me-1"></i> Eliminar para siempre
            </button>
            <span class="small text-muted ms-md-auto">
                <i class="bi bi-shield-check text-marca-green me-1"></i>
                Las clases con asistencias no se pueden eliminar para siempre: son historial de los reportes.
            </span>
        </div>

        {{-- Tabla de Registros Eliminados --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 border-0" style="width: 36px;">
                                <input class="form-check-input" type="checkbox" id="marcar-todas"
                                    title="Marcar todas">
                            </th>
                            <th class="border-0">Materia / Evento</th>
                            <th class="border-0">Laboratorio</th>
                            <th class="border-0">Día y Hora</th>
                            <th class="border-0">Profesor</th>
                            <th class="border-0">Eliminado el</th>
                            <th class="text-center pe-4 border-0">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($horariosBorrados as $horario)
                            @php
                                $conHistorial = $horario->asistencias_count + $horario->asistencias_profesor_count;
                                $profesor = $horario->user
                                    ? trim($horario->user->name . ' ' . $horario->user->apellido_paterno . ' ' . $horario->user->apellido_materno)
                                    : 'Profesor dado de baja';
                            @endphp
                            <tr class="shadow-hover border-bottom border-light">

                                <td class="ps-4">
                                    <input class="form-check-input casilla-horario" type="checkbox" name="ids[]"
                                        value="{{ $horario->id }}" form="form-lote">
                                </td>

                                {{-- Materia --}}
                                <td class="py-3">
                                    <span class="fw-bold text-dark">
                                        {{ $horario->materia->nombre_materia ?? 'Materia dada de baja' }}
                                    </span>
                                    <div class="small text-muted">{{ $horario->grupo->nombre_grupo ?? 'Sin grupo' }}</div>
                                    @if ($semestreId === 'todos' || ($semestreActivo && $horario->semestre_id != $semestreActivo->id))
                                        <div class="small" style="color: #8a6d00;">
                                            <i class="bi bi-calendar3 me-1"></i>{{ $horario->semestre->nombre ?? 'Sin semestre' }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Laboratorio --}}
                                <td>
                                    <span class="small fw-semibold text-secondary">
                                        {{ $horario->centroComputo->nombre_centro ?? '—' }}
                                    </span>
                                </td>

                                {{-- Día y Hora (Estilo Píldora) --}}
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-normal">
                                        <i class="bi bi-calendar-event text-marca-green me-1"></i>
                                        <span class="fw-bold">
                                            @if ($horario->fecha_especial)
                                                {{ \Carbon\Carbon::parse($horario->fecha_especial)->format('d/m/Y') }}
                                            @else
                                                {{ $horario->dia_semana }}
                                            @endif
                                        </span>
                                        <i class="bi bi-clock ms-2 me-1 text-muted"></i>
                                        {{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }}
                                        – {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
                                    </span>
                                </td>

                                {{-- Profesor --}}
                                <td>
                                    <span class="text-secondary fw-semibold small">{{ $profesor }}</span>
                                </td>

                                {{-- Fecha de Eliminación --}}
                                <td>
                                    <span class="text-danger small fw-bold">
                                        <i class="bi bi-calendar-x me-1"></i>
                                        {{ $horario->deleted_at->format('d/m/Y H:i') }}
                                    </span>
                                </td>

                                {{-- Acciones --}}
                                <td class="text-center pe-4 text-nowrap">
                                    <form action="{{ route('horarios.restaurar', $horario->id) }}" method="POST"
                                        class="d-inline form-restaurar">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-marca-green btn-sm rounded-pill shadow-sm px-3 fw-bold"
                                            title="Restaurar al Calendario">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar
                                        </button>
                                    </form>

                                    @if ($conHistorial > 0)
                                        <span class="d-inline-block" tabindex="0"
                                            title="Tiene {{ $conHistorial }} {{ $conHistorial == 1 ? 'asistencia registrada' : 'asistencias registradas' }}: se conserva para no perder el historial.">
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2"
                                                disabled>
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </span>
                                    @else
                                        <form action="{{ route('horarios.eliminarDefinitivo', $horario->id) }}"
                                            method="POST" class="d-inline form-eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2"
                                                title="Eliminar para siempre">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection

{{-- CONFIRMACIONES --}}
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const estilo = {
                background: '#ffffff',
                customClass: {
                    popup: 'rounded-4 shadow',
                    confirmButton: 'rounded-pill px-4 fw-bold',
                    cancelButton: 'rounded-pill px-4 fw-bold'
                }
            };

            function confirmar(opciones, alAceptar) {
                Swal.fire(Object.assign({
                    showCancelButton: true,
                    cancelButtonColor: '#6c757d',
                    cancelButtonText: 'Cancelar'
                }, estilo, opciones)).then((resultado) => {
                    if (resultado.isConfirmed) alAceptar();
                });
            }

            // Restaurar una
            document.querySelectorAll('.form-restaurar').forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();
                    confirmar({
                        title: '¿Restaurar este horario?',
                        text: 'La clase volverá al calendario. Si su espacio ya lo ocupa otra clase, se te avisará y no se restaurará.',
                        icon: 'question',
                        confirmButtonColor: '#009B4D',
                        confirmButtonText: 'Sí, restaurar'
                    }, () => this.submit());
                });
            });

            // Eliminar una para siempre
            document.querySelectorAll('.form-eliminar').forEach(formulario => {
                formulario.addEventListener('submit', function(e) {
                    e.preventDefault();
                    confirmar({
                        title: '¿Eliminar para siempre?',
                        text: 'Ya no se podrá recuperar. Sólo se permite porque esta clase no tiene asistencias registradas.',
                        icon: 'warning',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'Sí, eliminar'
                    }, () => this.submit());
                });
            });

            // Marcar varias
            const casillas = document.querySelectorAll('.casilla-horario');
            const marcarTodas = document.getElementById('marcar-todas');
            const botonesLote = document.querySelectorAll('.boton-lote');
            const contador = document.getElementById('contador-marcadas');

            function actualizar() {
                const marcadas = document.querySelectorAll('.casilla-horario:checked').length;
                if (contador) contador.textContent = marcadas;
                botonesLote.forEach(b => b.disabled = marcadas === 0);
                casillas.forEach(c => c.closest('tr').classList.toggle('fila-seleccionada', c.checked));
                if (marcarTodas) {
                    marcarTodas.checked = marcadas > 0 && marcadas === casillas.length;
                    marcarTodas.indeterminate = marcadas > 0 && marcadas < casillas.length;
                }
            }

            casillas.forEach(c => c.addEventListener('change', actualizar));
            marcarTodas?.addEventListener('change', function() {
                casillas.forEach(c => c.checked = this.checked);
                actualizar();
            });

            botonesLote.forEach(boton => {
                boton.addEventListener('click', function() {
                    const accion = this.dataset.accion;
                    const cuantas = document.querySelectorAll('.casilla-horario:checked').length;
                    const restaurar = accion === 'restaurar';

                    confirmar({
                        title: restaurar ? `¿Restaurar ${cuantas} horarios?` : `¿Eliminar ${cuantas} horarios para siempre?`,
                        text: restaurar ?
                            'Se restauran los que quepan; si el espacio de alguno ya está ocupado, se te dirá cuál y se quedará aquí.' :
                            'Los que tengan asistencias registradas se conservan. Los demás ya no se podrán recuperar.',
                        icon: restaurar ? 'question' : 'warning',
                        confirmButtonColor: restaurar ? '#009B4D' : '#dc3545',
                        confirmButtonText: restaurar ? 'Sí, restaurar' : 'Sí, eliminar'
                    }, () => {
                        document.getElementById('accion-lote').value = accion;
                        document.getElementById('form-lote').submit();
                    });
                });
            });

            actualizar();
        });
    </script>
@endpush
