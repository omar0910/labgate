@extends('layouts.alumnos')
@section('title', 'Mi Historial de Asistencia')

@section('content')
    <div class="row g-4">

        {{-- 1. ENCABEZADO PRINCIPAL --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border"
                            style="width: 55px; height: 55px;">
                            <i class="bi bi-clock-history fs-3" style="color: #009B4D;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Mi Historial de Asistencia</h4>
                            <p class="text-muted small mb-0 mt-1">Consulta y filtra todos tus registros de clases y uso
                                libre en los laboratorios.</p>
                        </div>
                    </div>

                    {{-- SELECTOR DE SEMESTRE (Diseño Píldora Premium) --}}
                    <form action="{{ route('alumno.historial') }}" method="GET"
                        class="d-flex align-items-center bg-light p-2 rounded-pill border shadow-sm">
                        {{-- Ocultamos los otros filtros para no perderlos si cambiamos el semestre --}}
                        @if (request('tipo'))
                            <input type="hidden" name="tipo" value="{{ request('tipo') }}">
                        @endif
                        @if (request('estado'))
                            <input type="hidden" name="estado" value="{{ request('estado') }}">
                        @endif

                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm ms-1 me-2"
                            style="width: 30px; height: 30px;">
                            <i class="bi bi-calendar3 text-marca-green" style="font-size: 0.85rem;"></i>
                        </div>
                        <label class="fw-bold text-muted small me-2 text-uppercase mb-0"
                            style="letter-spacing: 0.5px; font-size: 0.7rem;">Periodo:</label>
                        <select name="semestre_id"
                            class="form-select form-select-sm bg-transparent border-0 fw-bold text-dark shadow-none pe-4"
                            style="min-width: 220px; cursor: pointer;" onchange="this.form.submit()">
                            @foreach ($semestres as $sem)
                                <option value="{{ $sem->id }}" {{ $semestreId == $sem->id ? 'selected' : '' }}>
                                    {{ $sem->nombre }} {{ $sem->es_activo ? '(Activo)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>

        {{-- 2. PANEL DE FILTROS MODERNO --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('alumno.historial') }}" method="GET">
                        <input type="hidden" name="semestre_id" value="{{ $semestreId }}">
                        <div class="row g-3 align-items-end">

                            {{-- Filtro: Materia --}}
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 0.7rem;">Materia / Clase</label>
                                <select name="materia_id" class="form-select bg-light border-0 shadow-sm">
                                    <option value="">Todas las materias</option>
                                    @foreach ($materiasFilter as $materia)
                                        <option value="{{ $materia->id }}"
                                            {{ request('materia_id') == $materia->id ? 'selected' : '' }}>
                                            {{ $materia->nombre_materia }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Filtro: Tipo --}}
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-muted small text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 0.7rem;">Tipo</label>
                                <select name="tipo" class="form-select bg-light border-0 shadow-sm">
                                    <option value="">Todo</option>
                                    <option value="Clase" {{ request('tipo') == 'Clase' ? 'selected' : '' }}>Clases
                                    </option>
                                    @if (!request('materia_id'))
                                        <option value="Uso Libre" {{ request('tipo') == 'Uso Libre' ? 'selected' : '' }}>
                                            Uso
                                            Libre</option>
                                    @endif
                                </select>
                            </div>

                            {{-- Filtro: Estado --}}
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-muted small text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 0.7rem;">Estado</label>
                                <select name="estado" class="form-select bg-light border-0 shadow-sm">
                                    <option value="">Todos</option>
                                    <option value="presente" {{ request('estado') == 'presente' ? 'selected' : '' }}>
                                        Asistencias</option>
                                    <option value="falta" {{ request('estado') == 'falta' ? 'selected' : '' }}>Faltas
                                    </option>
                                    <option value="justificado" {{ request('estado') == 'justificado' ? 'selected' : '' }}>
                                        Justificados</option>
                                </select>
                            </div>

                            {{-- Filtro: Fechas --}}
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 0.7rem;">Fechas</label>
                                <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                    <input type="date" name="fecha_inicio" class="form-control bg-light border-0 px-2"
                                        value="{{ request('fecha_inicio') }}">
                                    <span class="input-group-text border-0 bg-light px-1 text-muted">-</span>
                                    <input type="date" name="fecha_fin" class="form-control bg-light border-0 px-2"
                                        value="{{ request('fecha_fin') }}">
                                </div>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-marca-black shadow-sm w-100 fw-bold"
                                    title="Aplicar Filtros">
                                    <i class="bi bi-search me-1"></i> Filtrar
                                </button>
                                @if (request()->hasAny(['materia_id', 'tipo', 'estado', 'fecha_inicio', 'fecha_fin']))
                                    <a href="{{ route('alumno.historial', ['semestre_id' => $semestreId]) }}"
                                        class="btn btn-light border shadow-sm text-danger" title="Limpiar Filtros">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- 3. TABLA DE RESULTADOS --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">

                {{-- Cabecera de la tabla --}}
                <div
                    class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="mb-0 fw-bold text-dark">
                        Resultados de Búsqueda
                    </h6>
                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2 shadow-sm">
                        <i class="bi bi-list-ul me-1"></i> {{ $asistencias->total() }} registros
                    </span>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3 border-0 text-muted small text-uppercase"
                                        style="letter-spacing: 0.5px;">Fecha y Hora</th>
                                    <th class="py-3 border-0 text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                                        Actividad / Materia</th>
                                    {{-- En celular no caben: el equipo y la nota van con la actividad --}}
                                    <th class="py-3 text-center border-0 text-muted small text-uppercase d-none d-md-table-cell"
                                        style="letter-spacing: 0.5px;">PC #</th>
                                    <th class="py-3 text-center border-0 text-muted small text-uppercase"
                                        style="letter-spacing: 0.5px;">Estado</th>
                                    <th class="py-3 text-center border-0 text-muted small text-uppercase d-none d-md-table-cell"
                                        style="letter-spacing: 0.5px;">Comentarios</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($asistencias as $registro)
                                    <tr>
                                        {{-- Columna: Fecha --}}
                                        <td class="ps-4">
                                            <span
                                                class="fw-bold text-dark fs-6">{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</span>
                                            <br>
                                            <small class="text-muted fw-bold" style="font-size: 0.75rem;">
                                                <i
                                                    class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('h:i A') }}
                                            </small>

                                            {{-- Uso libre: a qué hora salió y cuánto tiempo estuvo --}}
                                            @if ($registro->tipo == 'Uso Libre')
                                                <br>
                                                <small class="text-muted" style="font-size: 0.75rem;">
                                                    @if ($registro->fecha_hora_salida)
                                                        @php
                                                            $minutos = (int) \Carbon\Carbon::parse($registro->fecha_hora_registro)->diffInMinutes(\Carbon\Carbon::parse($registro->fecha_hora_salida));
                                                        @endphp
                                                        <span class="text-nowrap"><i class="bi bi-box-arrow-right me-1"></i>{{ \Carbon\Carbon::parse($registro->fecha_hora_salida)->format('h:i A') }}</span>
                                                        · <span class="text-nowrap">{{ $minutos >= 60 ? intdiv($minutos, 60) . ' h ' . ($minutos % 60 ? $minutos % 60 . ' min' : '') : $minutos . ' min' }}</span>
                                                    @else
                                                        <i class="bi bi-hourglass-split me-1"></i>Sin salida registrada
                                                    @endif
                                                </small>
                                            @endif
                                        </td>

                                        {{-- Columna: Actividad --}}
                                        <td>
                                            @if ($registro->tipo == 'Clase' && $registro->horario)
                                                <div class="text-dark fw-bold line-clamp-2" style="max-width: 250px;">
                                                    {{ $registro->horario?->materia?->nombre_materia ?? 'Materia Eliminada' }}
                                                </div>
                                                <small class="text-muted">Prof: {{ $registro->horario?->user?->name }}
                                                    {{ $registro->horario?->user?->apellido_paterno }}</small>
                                            @else
                                                <span class="text-marca-green fw-bold">Uso Libre</span>
                                                <br>
                                                <small
                                                    class="text-muted">{{ $registro->centroComputo?->nombre_centro ?? 'Laboratorio' }}</small>
                                            @endif

                                            {{-- En celular, el equipo y la nota van aquí (sus columnas no caben) --}}
                                            @if ($registro->equipo_personal || $registro->numero_maquina || $registro->comentario)
                                                <div class="d-md-none small text-muted mt-1">
                                                    @if ($registro->equipo_personal)
                                                        <i class="bi bi-laptop"></i> Equipo personal
                                                    @elseif ($registro->numero_maquina)
                                                        <i class="bi bi-pc-display"></i> PC #{{ $registro->numero_maquina }}
                                                    @endif
                                                    @if ($registro->comentario)
                                                        <button type="button"
                                                            class="btn btn-sm btn-light border rounded-pill px-2 py-0 ms-1 btn-ver-nota"
                                                            data-nota="{{ $registro->comentario }}">
                                                            <i class="bi bi-chat-text text-marca-green"></i> Nota
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Columna: Máquina --}}
                                        <td class="text-center d-none d-md-table-cell">
                                            <div class="bg-light border rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-dark shadow-sm"
                                                style="width: 38px; height: 38px; font-size: 0.9rem;"
                                                title="{{ $registro->equipo_personal ? 'Equipo personal' : '' }}">
                                                @if ($registro->equipo_personal)
                                                    <i class="bi bi-laptop"></i>
                                                @else
                                                    {{ $registro->numero_maquina ?? '-' }}
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Columna: Estado (Estilo Progreso) --}}
                                        <td class="text-center">
                                            @if ($registro->estado == 'presente')
                                                <span
                                                    class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                                                    Asistió
                                                </span>
                                            @elseif ($registro->estado == 'justificado')
                                                <span
                                                    class="badge bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-pill px-3 py-1 fw-bold"
                                                    style="color: #d39e00;">
                                                    Justificado
                                                </span>
                                            @elseif ($registro->estado == 'falta')
                                                <span
                                                    class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                                                    Falta
                                                </span>
                                            @else
                                                <span
                                                    class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                                                    {{ ucfirst($registro->estado) }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Columna: Comentarios --}}
                                        <td class="text-center d-none d-md-table-cell">
                                            @if ($registro->comentario)
                                                {{-- La nota va en un atributo, no dentro del JavaScript: con un
                                                     apóstrofo o un salto de línea el botón dejaba de abrir --}}
                                                <button type="button"
                                                    class="btn btn-sm btn-light border shadow-sm rounded-pill px-3 btn-ver-nota"
                                                    data-nota="{{ $registro->comentario }}">
                                                    <i class="bi bi-chat-text text-marca-green"></i> Ver nota
                                                </button>
                                            @else
                                                <span class="text-muted small opacity-50">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    {{-- Estado Vacío (Empty State Institucional) --}}
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="d-inline-flex align-items-center justify-content-center bg-light border rounded-circle mb-3 shadow-sm"
                                                style="width: 80px; height: 80px;">
                                                <i class="bi bi-search fs-1 text-muted opacity-50"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">No se encontraron resultados</h5>
                                            <p class="text-muted mx-auto mb-0" style="max-width: 400px;">
                                                Intenta ajustando los filtros de búsqueda en la parte superior para
                                                encontrar tus registros.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Paginación --}}
                <div class="card-footer bg-white border-0 py-4">
                    <div class="d-flex justify-content-center">
                        {{ $asistencias->links() }}
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Observación del docente, tal como la escribió (con sus saltos de línea)
        document.querySelectorAll('.btn-ver-nota').forEach(function(boton) {
            boton.addEventListener('click', function() {
                Swal.fire({
                    title: 'Observación del Docente',
                    text: this.dataset.nota,
                    icon: 'info',
                    confirmButtonColor: '#009B4D',
                    didOpen: function() {
                        Swal.getHtmlContainer().style.whiteSpace = 'pre-line';
                    }
                });
            });
        });
    </script>
@endpush
