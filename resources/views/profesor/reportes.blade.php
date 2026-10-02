@extends('layouts.profesor')
@section('title', 'Centro de Reportes')

@section('content')
    @include('partials.estilos-progreso')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .shadow-hover {
            transition: all 0.3s ease;
        }

        .shadow-hover:hover {
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
            transform: translateY(-3px);
        }

        .dato-clase {
            background-color: #f8f9fa;
            border-radius: .75rem;
            padding: .5rem .75rem;
            text-align: center;
            height: 100%;
        }

        .dato-clase .numero {
            display: block;
            font-weight: 700;
            font-size: 1.25rem;
            line-height: 1.2;
        }

        .dato-clase .etiqueta {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #6c757d;
        }

        .fila-descarga {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .4rem 0;
        }

        .fila-descarga + .fila-descarga {
            border-top: 1px solid #eef0f2;
        }
    </style>

    @php
        $minimo = \App\Support\ProgresoDelAlumno::MINIMO;
        $dias = ['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb', 'Domingo' => 'Dom'];
    @endphp

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black">
                    <i class="bi bi-file-earmark-bar-graph text-marca-green me-2"></i> Centro de Reportes
                </h3>
                <p class="text-muted small mb-0 mt-1">Cómo va la asistencia de cada clase, y sus listas y estadísticas para
                    descargar.</p>
            </div>

            {{-- SELECTOR DE SEMESTRE --}}
            <form action="{{ route('profesor.reportes') }}" method="GET"
                class="d-flex align-items-center bg-light p-2 rounded-pill border shadow-sm">
                <i class="bi bi-mortarboard-fill text-marca-green ms-2 me-2"></i>
                <select name="semestre_id" id="semestre_id"
                    class="form-select form-select-sm bg-transparent border-0 fw-bold shadow-none pe-4"
                    style="min-width: 200px; cursor: pointer;" onchange="this.form.submit()" aria-label="Periodo escolar">
                    @foreach ($semestres as $sem)
                        <option value="{{ $sem->id }}" {{ $semestreSeleccionadoId == $sem->id ? 'selected' : '' }}>
                            {{ $sem->nombre }} {!! $sem->es_activo ? '&#9733; (Actual)' : '' !!}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- MENSAJES TEMPORALES DE ÉXITO --}}
        @if (session('success'))
            <div class="alert alert-info alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- RESUMEN DEL PERIODO --}}
        @if ($clasesUnicas->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fs-6 fw-semibold">
                    <i class="bi bi-journal-bookmark text-marca-green me-1"></i>
                    {{ $general['clases'] }} {{ $general['clases'] == 1 ? 'clase' : 'clases' }}
                </span>
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fs-6 fw-semibold">
                    <i class="bi bi-people text-marca-green me-1"></i>
                    {{ $general['alumnos'] }} {{ $general['alumnos'] == 1 ? 'alumno' : 'alumnos' }}
                </span>
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fs-6 fw-semibold">
                    <i class="bi bi-calendar-check text-marca-green me-1"></i>
                    {{ $general['impartidas'] }} {{ $general['impartidas'] == 1 ? 'sesión impartida' : 'sesiones impartidas' }}
                </span>
                <span class="badge rounded-pill px-3 py-2 fs-6 fw-semibold border {{ $general['en_riesgo'] > 0 ? 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25' : 'bg-success bg-opacity-10 text-success border-success border-opacity-25' }}">
                    <i class="bi {{ $general['en_riesgo'] > 0 ? 'bi-exclamation-triangle' : 'bi-shield-check' }}"></i>
                    {{ $general['en_riesgo'] }} {{ $general['en_riesgo'] == 1 ? 'alumno' : 'alumnos' }} en riesgo
                </span>
            </div>
        @endif

        {{-- RESULTADOS: TARJETAS DE MATERIAS --}}
        @if ($semestreSeleccionadoId)
            <div class="row g-4">
                @forelse ($clasesUnicas as $clase)
                    @php
                        $e = $clase->estadisticas;
                        $promedio = $e['resumen']['promedio'];
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm border-0 rounded-4 shadow-hover position-relative overflow-hidden">
                            <div class="position-absolute top-0 start-0 w-100 bg-marca-green" style="height: 5px;"></div>

                            <div class="card-body p-4 d-flex flex-column">
                                {{-- La clase --}}
                                <h5 class="card-title fw-bold text-marca-black mb-2" style="line-height: 1.2;">
                                    {{ $clase->materia->nombre_materia ?? 'Clase' }}
                                </h5>
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <span class="badge bg-light text-dark border px-2 py-1 text-wrap text-start">
                                        <i class="bi bi-people-fill text-secondary"></i>
                                        {{ $clase->grupo->nombre_grupo ?? 'Sin grupo' }}
                                    </span>
                                    @foreach ($e['horarios']->where('tipo_reserva', '!=', 'especial')->sortBy(fn($s) => $s->ordenDeSesion()) as $sesion)
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="bi bi-clock text-marca-green"></i>
                                            {{ $dias[$sesion->dia_semana] ?? $sesion->dia_semana }}
                                            {{ \Carbon\Carbon::parse($sesion->hora_inicio)->format('H:i') }}
                                        </span>
                                    @endforeach
                                </div>

                                {{-- Cómo va --}}
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="anillo-progreso {{ $promedio !== null && $promedio < $minimo ? 'en-riesgo' : '' }}"
                                        style="--valor: {{ $promedio ?? 0 }}; --tamano: 72px;"
                                        title="Asistencia promedio del grupo">
                                        <span>{{ $promedio !== null ? $promedio . '%' : '—' }}</span>
                                    </div>
                                    <div class="row g-2 flex-grow-1">
                                        <div class="col-4">
                                            <div class="dato-clase">
                                                <span class="numero text-dark">{{ $e['resumen']['alumnos'] }}</span>
                                                <span class="etiqueta">Alumnos</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="dato-clase">
                                                <span class="numero text-dark">{{ $e['totalClases'] }}</span>
                                                <span class="etiqueta">Clases</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="dato-clase" style="{{ $e['resumen']['en_riesgo'] > 0 ? 'background-color: #fff5f5;' : '' }}">
                                                <span class="numero {{ $e['resumen']['en_riesgo'] > 0 ? 'text-danger' : 'text-dark' }}">{{ $e['resumen']['en_riesgo'] }}</span>
                                                <span class="etiqueta">En riesgo</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ route('profesor.ver-reporte', ['horario_id' => $clase->id]) }}"
                                    class="btn btn-marca-green rounded-pill fw-bold shadow-sm mb-3">
                                    <i class="bi bi-bar-chart-line-fill me-1"></i> Ver estadísticas
                                </a>

                                {{-- Descargas --}}
                                <div class="mt-auto border rounded-3 px-3 py-1">
                                    <div class="fila-descarga">
                                        <span class="small fw-bold text-muted"><i class="bi bi-list-ol me-1"></i> Lista de alumnos</span>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('profesor.reportes.lista-pdf', $clase->id) }}"
                                                class="btn btn-light border fw-bold" title="Lista de alumnos en PDF">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger"></i> PDF
                                            </a>
                                            <a href="{{ route('profesor.reportes.lista-excel', $clase->id) }}"
                                                class="btn btn-light border fw-bold" title="Lista de alumnos en Excel">
                                                <i class="bi bi-file-earmark-excel-fill text-success"></i> Excel
                                            </a>
                                        </div>
                                    </div>
                                    <div class="fila-descarga">
                                        <span class="small fw-bold text-muted"><i class="bi bi-graph-up me-1"></i> Estadísticas</span>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('profesor.reportes.estadisticas-pdf', $clase->id) }}"
                                                class="btn btn-light border fw-bold" title="Estadísticas en PDF">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger"></i> PDF
                                            </a>
                                            <a href="{{ route('profesor.reportes.estadisticas-excel', $clase->id) }}"
                                                class="btn btn-light border fw-bold" title="Estadísticas en Excel">
                                                <i class="bi bi-file-earmark-excel-fill text-success"></i> Excel
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4 bg-light">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-folder-x fs-1 text-secondary opacity-50 d-block mb-3"></i>
                                <h4 class="fw-bold text-dark mb-2">Sin clases registradas</h4>
                                <p class="text-muted mb-0">No se encontraron materias asignadas a tu cuenta en el semestre
                                    seleccionado.</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        @endif

    </div>

@endsection
