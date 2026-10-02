@extends('layouts.profesor')
{{-- El nombre del grupo ya incluye el de la materia: repetirlos hacía un título larguísimo --}}
@section('title', 'Estadísticas: ' . ($materia->nombre_materia ?? 'Clase'))

{{-- Se abre desde un pase de lista (el de hoy o uno del "Historial") o desde "Reportes" --}}
@section('menu', in_array(request('seccion'), ['hoy', 'historial'], true) ? request('seccion') : 'reportes')

@section('content')
    @include('partials.estilos-progreso')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        /* Barra de progreso verde institucional */
        .progress-bar-marca {
            background-color: var(--marca-green);
        }

        .tarjeta-dato {
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            height: 100%;
        }
    </style>

    @php
        $minimo = \App\Support\ProgresoDelAlumno::MINIMO;
        $dias = ['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb', 'Domingo' => 'Dom'];
    @endphp

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 pb-3 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-bar-chart-line-fill text-marca-green me-2"></i>
                    Estadísticas de Asistencia</h3>
                <p class="text-muted small mb-1 mt-1">
                    <span class="fw-bold">{{ $materia->nombre_materia ?? 'Clase' }}</span>
                    @if ($horario->semestre)
                        · Periodo {{ $horario->semestre->nombre }}
                    @endif
                </p>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-light text-dark border text-wrap text-start"><i class="bi bi-people me-1"></i> Grupo:
                        {{ $grupo->nombre_grupo ?? 'Sin grupo' }}</span>
                    @foreach ($sesiones->sortBy(fn($s) => $s->ordenDeSesion()) as $sesion)
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-clock text-marca-green me-1"></i>
                            {{ $sesion->fecha_especial ? \Carbon\Carbon::parse($sesion->fecha_especial)->format('d/m') : $dias[$sesion->dia_semana] ?? $sesion->dia_semana }}
                            {{ \Carbon\Carbon::parse($sesion->hora_inicio)->format('H:i') }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                {{-- Descargas de esta misma clase --}}
                <div class="btn-group shadow-sm" role="group" aria-label="Descargar estadísticas">
                    <a href="{{ route('profesor.reportes.estadisticas-pdf', $horario->id) }}"
                        class="btn btn-light border rounded-start-pill px-3 fw-bold" title="Descargar en PDF">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> PDF
                    </a>
                    <a href="{{ route('profesor.reportes.estadisticas-excel', $horario->id) }}"
                        class="btn btn-light border rounded-end-pill px-3 fw-bold" title="Descargar en Excel">
                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> Excel
                    </a>
                </div>
                <a href="{{ $volver }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        {{-- RESUMEN DEL GRUPO --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="tarjeta-dato bg-light border">
                    <span class="text-muted small fw-bold text-uppercase">Alumnos</span>
                    <span class="d-block fw-bold fs-2 text-dark">{{ $resumen['alumnos'] }}</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tarjeta-dato bg-light border">
                    <span class="text-muted small fw-bold text-uppercase">Clases impartidas</span>
                    <span class="d-block fw-bold fs-2 text-dark">{{ $totalClases }}</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tarjeta-dato border d-flex align-items-center gap-3" style="background-color: #f0faf4;">
                    <div class="anillo-progreso {{ ($resumen['promedio'] ?? 100) < $minimo ? 'en-riesgo' : '' }}"
                        style="--valor: {{ $resumen['promedio'] ?? 0 }}; --tamano: 58px;">
                        <span>{{ $resumen['promedio'] !== null ? $resumen['promedio'] . '%' : '—' }}</span>
                    </div>
                    <span class="text-muted small fw-bold text-uppercase">Asistencia promedio</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tarjeta-dato border {{ $resumen['en_riesgo'] > 0 ? 'border-danger' : '' }}"
                    style="background-color: {{ $resumen['en_riesgo'] > 0 ? '#fff5f5' : '#f8f9fa' }};">
                    <span class="small fw-bold text-uppercase {{ $resumen['en_riesgo'] > 0 ? 'text-danger' : 'text-muted' }}">En
                        riesgo (menos de {{ $minimo }}%)</span>
                    <span class="d-block fw-bold fs-2 {{ $resumen['en_riesgo'] > 0 ? 'text-danger' : 'text-dark' }}">{{ $resumen['en_riesgo'] }}</span>
                </div>
            </div>
        </div>

        {{-- TABLA DE RESULTADOS --}}
        <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">

            <div
                class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold text-dark mb-0">Rendimiento del Alumnado</h6>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="search" id="buscar-alumno" class="form-control form-control-sm rounded-pill"
                        placeholder="Buscar nombre o matrícula" style="min-width: 220px;">
                    @if ($resumen['en_riesgo'] > 0)
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold" id="solo-riesgo">
                            <i class="bi bi-exclamation-triangle me-1"></i> Sólo en riesgo ({{ $resumen['en_riesgo'] }})
                        </button>
                    @endif
                </div>
            </div>

            <div class="card-body p-0 mt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 border-0 d-none d-md-table-cell">#</th>
                                <th class="border-0 d-none d-md-table-cell">Matrícula</th>
                                <th class="border-0 ps-3 ps-md-2">Nombre del Alumno</th>
                                <th class="text-center border-0">Asistió</th>
                                <th class="text-center border-0 d-none d-md-table-cell">Justif.</th>
                                <th class="text-center border-0">Faltó</th>
                                <th class="text-center border-0">Progreso</th>
                                <th class="text-center pe-4 border-0 d-none d-sm-table-cell">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alumnosData as $index => $alumno)
                                <tr class="border-bottom border-light fila-alumno"
                                    data-riesgo="{{ $alumno['en_riesgo'] ? 1 : 0 }}"
                                    data-buscar="{{ mb_strtolower(\Illuminate\Support\Str::ascii($alumno['nombre_completo'] . ' ' . $alumno['matricula'])) }}">
                                    <td class="ps-4 text-muted fw-bold d-none d-md-table-cell">{{ $index + 1 }}</td>

                                    <td class="d-none d-md-table-cell">
                                        <span class="fw-bold text-dark font-monospace">{{ $alumno['matricula'] }}</span>
                                    </td>

                                    <td class="fw-semibold text-dark ps-3 ps-md-2">
                                        {{ $alumno['nombre_completo'] }}
                                        <span class="d-md-none d-block small text-muted font-monospace">{{ $alumno['matricula'] }}</span>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-light text-success border px-2 py-1 fs-6">{{ $alumno['presentes'] }}</span>
                                    </td>

                                    <td class="text-center d-none d-md-table-cell">
                                        <span class="badge bg-light border px-2 py-1 fs-6" style="color: #b58800;">{{ $alumno['justificadas'] }}</span>
                                    </td>

                                    {{-- Faltas (incluye las clases en que no se registró y sus compañeros sí) --}}
                                    <td class="text-center">
                                        <span class="badge bg-light text-danger border px-2 py-1 fs-6"
                                            @if ($alumno['sin_registro'] > 0) title="{{ $alumno['sin_registro'] }} sin registrarse (sus compañeros sí)" @endif>
                                            {{ $alumno['faltas'] }}
                                        </span>
                                        @if ($alumno['sin_registro'] > 0)
                                            <span class="d-block text-muted" style="font-size: .7rem;">{{ $alumno['sin_registro'] }} sin registro</span>
                                        @endif
                                    </td>

                                    {{-- Barra de Porcentaje --}}
                                    <td class="text-center px-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="progress flex-grow-1 shadow-sm"
                                                style="height: 8px; max-width: 100px; min-width: 60px;">
                                                <div class="progress-bar {{ $alumno['en_riesgo'] ? 'bg-danger' : 'progress-bar-marca' }}"
                                                    role="progressbar" style="width: {{ $alumno['porcentaje'] }}%;">
                                                </div>
                                            </div>
                                            <span
                                                class="ms-2 small fw-bold {{ $alumno['en_riesgo'] ? 'text-danger' : 'text-marca-green' }}">
                                                {{ $alumno['total'] > 0 ? $alumno['porcentaje'] . '%' : '—' }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Estado --}}
                                    <td class="text-center pe-4 d-none d-sm-table-cell">
                                        @if ($alumno['total'] === 0)
                                            <span class="estado-clase no-impartida">Sin clases</span>
                                        @elseif ($alumno['en_riesgo'])
                                            <span class="estado-clase falta"><i class="bi bi-exclamation-triangle"></i> Riesgo</span>
                                        @else
                                            <span class="estado-clase asistio"><i class="bi bi-shield-check"></i> Regular</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted border-0">
                                        <i class="bi bi-people fs-1 d-block mb-3 opacity-25"></i>
                                        <span class="d-block fw-bold text-dark fs-5">Sin estadísticas</span>
                                        <small>No hay alumnos en este grupo para generar el reporte.</small>
                                    </td>
                                </tr>
                            @endforelse
                            <tr id="sin-coincidencias" class="d-none">
                                <td colspan="8" class="text-center py-4 text-muted">Ningún alumno coincide con la búsqueda.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Cómo se cuenta --}}
        <p class="small text-muted mb-0">
            <i class="bi bi-info-circle text-marca-green me-1"></i>
            Se cuenta igual que lo que cada alumno ve en su Progreso: las faltas incluyen las clases en que no se registró
            y sus compañeros sí. No cuentan las clases de antes de su inscripción al grupo ni las que quedaron como no
            impartidas (con tu falta o justificación registrada). Las justificadas cuentan como asistencia.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        // Buscar alumno y "sólo en riesgo": sólo muestran u ocultan filas
        (function() {
            const buscar = document.getElementById('buscar-alumno');
            const riesgo = document.getElementById('solo-riesgo');
            let soloRiesgo = false;

            function aplicar() {
                const texto = (buscar.value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
                let visibles = 0;
                document.querySelectorAll('.fila-alumno').forEach(function(fila) {
                    const ver = (!texto || fila.dataset.buscar.includes(texto)) && (!soloRiesgo || fila.dataset.riesgo === '1');
                    fila.classList.toggle('d-none', !ver);
                    if (ver) visibles++;
                });
                document.getElementById('sin-coincidencias').classList.toggle('d-none', visibles > 0 || !document.querySelector('.fila-alumno'));
            }

            if (buscar) buscar.addEventListener('input', aplicar);
            if (riesgo) {
                riesgo.addEventListener('click', function() {
                    soloRiesgo = !soloRiesgo;
                    riesgo.classList.toggle('btn-danger', soloRiesgo);
                    riesgo.classList.toggle('btn-outline-danger', !soloRiesgo);
                    aplicar();
                });
            }
        })();
    </script>
@endpush
