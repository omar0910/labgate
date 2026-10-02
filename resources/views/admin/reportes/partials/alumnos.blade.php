<div class="row g-4">
    <div class="col-12">

        {{-- 1. TARJETA INDEPENDIENTE PARA ENCABEZADO Y BOTONES --}}
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap"
                style="border-radius: 1rem;">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-mortarboard-fill text-marca-green me-2"></i> Reporte de Actividad de Alumnos
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Análisis detallado de asistencias por materia y laboratorios.
                    </p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.alumnos.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                    </a>
                    <a href="{{ route('admin.reportes.alumnos.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-success rounded-pill fw-bold shadow-sm px-4" style="background-color: #198754;">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </a>
                </div>
            </div>
        </div>

        {{-- 2. TARJETA INDEPENDIENTE PARA LA TABLA --}}
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                @include('partials.buscador-en-tabla', [
                    'tabla' => 'tablaReporteAlumnos',
                    'etiqueta' => 'Buscar alumno',
                    'ayuda' => 'Matrícula, nombre o apellidos...',
                    'singular' => 'alumno',
                    'plural' => 'alumnos',
                ])
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaReporteAlumnos">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3 border-0 rounded-start">Alumno</th>
                                <th class="border-0">Laboratorios</th>
                                <th class="text-center border-0">Clases asistidas</th>
                                <th class="text-center border-0">Uso Libre</th>
                                <th class="text-center border-0 rounded-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reporteAlumnos as $user_id => $asistencias)
                                @php
                                    $alumno = $asistencias->first()->user;

                                    // Solo contamos como "Asistencia a clase" si estuvo presente o justificado
                                    $clasesCount = $asistencias
                                        ->where('tipo', 'Clase')
                                        ->whereIn('estado', ['presente', 'justificado', null])
                                        ->count();

                                    $usoLibreCount = $asistencias->where('tipo', 'Uso Libre')->count();
                                    $labsVisitados = $asistencias
                                        ->pluck('centroComputo.nombre_centro')
                                        ->unique()
                                        ->filter();
                                @endphp
                                <tr class="border-bottom"
                                    data-buscar="{{ $alumno->name }} {{ $alumno->apellido_paterno }} {{ $alumno->apellido_materno }} {{ $alumno->matricula }} {{ $labsVisitados->implode(' ') }}">
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark">{{ $alumno->name }}
                                            {{ $alumno->apellido_paterno }}
                                            {{ $alumno->apellido_materno }}</div>
                                        <small class="text-muted">Matrícula: {{ $alumno->matricula ?? 'S/M' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($labsVisitados as $lab)
                                                <span
                                                    class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill"
                                                    style="font-size: 0.7rem;">{{ $lab }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">{{ $clasesCount }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3">{{ $usoLibreCount }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3 shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalKardex{{ $user_id }}">
                                            <i class="bi bi-eye-fill"></i> Ver Kárdex
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No hay datos disponibles.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODALES DE KÁRDEX (Mantenidos intactos) --}}
@foreach ($reporteAlumnos as $user_id => $asistencias)
    @php
        $alumno = $asistencias->first()->user;

        // 1. Agrupamos asistencias de CLASE por Materia y Laboratorio
        $detallesClase = $asistencias->where('tipo', 'Clase')->groupBy(function ($item) {
            return ($item->horario->materia->nombre_materia ?? 'N/A') .
                ' - ' .
                ($item->centroComputo->nombre_centro ?? 'N/A');
        });

        // 2. Agrupamos USO LIBRE por Laboratorio
        $detallesUsoLibre = $asistencias->where('tipo', 'Uso Libre')->groupBy(function ($item) {
            return $item->centroComputo->nombre_centro ?? 'Laboratorio No Identificado';
        });
    @endphp

    <div class="modal fade" id="modalKardex{{ $user_id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0 ps-4 pt-4">
                    <div>
                        <h5 class="modal-title fw-bold text-dark">Kárdex de Asistencias</h5>
                        <p class="text-muted small mb-0">Semestre: {{ $semestreSeleccionado->nombre ?? 'Actual' }}</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    {{-- Perfil del Alumno --}}
                    <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-4">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm me-3"
                            style="width: 60px; height: 60px;">
                            <i class="bi bi-person-badge text-marca-green fs-2"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">{{ $alumno->name }} {{ $alumno->apellido_paterno }}
                                {{ $alumno->apellido_materno }}</h5>
                            <span class="badge bg-marca-green text-white rounded-pill">Matrícula:
                                {{ $alumno->matricula }}</span>
                        </div>
                    </div>

                    {{-- SECCIÓN: ASISTENCIA A CLASES --}}
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-mortarboard-fill text-marca-green me-2"></i>
                        Desglose Académico (Clases)</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0 small text-uppercase ps-3 py-2">Materia / Grupo</th>
                                    <th class="border-0 small text-uppercase py-2">Laboratorio</th>
                                    <th class="border-0 small text-uppercase text-center py-2">Asistió</th>
                                    <th class="border-0 small text-uppercase text-center py-2 pe-3">Faltó</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detallesClase as $key => $items)
                                    @php
                                        $primera = $items->first();
                                        // Filtramos los estados basándonos en cómo guarda el profesor
                                        $asistenciasreales = $items
                                            ->whereIn('estado', ['presente', 'justificado', null])
                                            ->count();
                                        $faltas = $items->where('estado', 'falta')->count();
                                    @endphp
                                    <tr class="border-bottom">
                                        <td class="ps-3 py-2">
                                            <div class="fw-bold text-dark">
                                                {{ $primera->horario->materia->nombre_materia ?? 'N/A' }}</div>
                                            <small class="text-muted">Grupo:
                                                {{ $primera->horario->grupo->nombre_grupo ?? 'N/A' }}</small>
                                        </td>
                                        <td class="py-2"><span
                                                class="text-dark small">{{ $primera->centroComputo->nombre_centro ?? 'N/A' }}</span>
                                        </td>
                                        <td class="text-center py-2">
                                            <span
                                                class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">{{ $asistenciasreales }}</span>
                                        </td>
                                        <td class="text-center py-2 pe-3">
                                            @if ($faltas > 0)
                                                <span
                                                    class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">{{ $faltas }}</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">Sin registros de
                                            clases.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- SECCIÓN: USO LIBRE --}}
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-workspace text-warning me-2"></i> Desglose
                        de Uso Libre</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0 small text-uppercase ps-3 py-2">Laboratorio</th>
                                    <th class="border-0 small text-uppercase text-center py-2 pe-3">Total Ingresos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detallesUsoLibre as $labNombre => $items)
                                    <tr class="border-bottom">
                                        <td class="ps-3 py-2">
                                            <div class="fw-bold text-dark small">{{ $labNombre }}</div>
                                        </td>
                                        <td class="text-center py-2 pe-3"><span
                                                class="badge bg-warning bg-opacity-10 text-dark rounded-pill px-3">{{ $items->count() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-4 text-muted small">Sin registros de
                                            uso libre.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
