<div class="row g-4">
    <div class="col-12">

        {{-- 1. TARJETA INDEPENDIENTE PARA ENCABEZADO Y BOTONES --}}
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap"
                style="border-radius: 1rem;">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-person-badge-fill text-marca-green me-2"></i> Reporte de Docentes y Asistencia
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Carga académica y cumplimiento de asistencia de profesores.</p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.docentes.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                    </a>
                    <a href="{{ route('admin.reportes.docentes.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-success rounded-pill fw-bold shadow-sm px-4" style="background-color: #198754;">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </a>
                </div>
            </div>
        </div>

        {{-- 2. TARJETA INDEPENDIENTE PARA LA TABLA CON SCROLL --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-body p-0">
                @include('partials.buscador-en-tabla', [
                    'tabla' => 'tablaReporteDocentes',
                    'etiqueta' => 'Buscar docente',
                    'ayuda' => 'Nombre, usuario o materia...',
                    'singular' => 'docente',
                    'plural' => 'docentes',
                ])
                <div class="table-responsive" style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tablaReporteDocentes">
                        <thead class="table-light sticky-top" style="z-index: 1;">
                            <tr>
                                <th class="ps-4 py-3 border-0 rounded-start">Docente</th>
                                <th class="border-0">Materias Asignadas</th>
                                <th class="text-center border-0">Total Clases</th>
                                <th class="text-center border-0 rounded-end">Asistencia (Cumplimiento)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reporteDocentes as $docente)
                                <tr
                                    data-buscar="{{ $docente['profesor'] }} {{ $docente['username'] }} {{ collect($docente['materias'])->implode(' ') }}">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center py-2">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 text-marca-green fw-bold shadow-sm border"
                                                style="width: 40px; height: 40px;">
                                                {{ substr($docente['profesor'], 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">
                                                    {{ $docente['profesor'] }}
                                                </div>
                                                <small class="text-muted">{{ $docente['username'] }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @foreach ($docente['materias'] as $materia)
                                            <span
                                                class="badge bg-light text-dark border mb-1">{{ $materia }}</span><br>
                                        @endforeach
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                            <div class="d-flex align-items-baseline gap-1">
                                                <h5 class="mb-0 fw-bold text-marca-green"
                                                    title="Clases que debieron darse hasta hoy">
                                                    {{ $docente['esperadas_hoy'] }}
                                                </h5>
                                                <span class="text-muted fs-5">/</span>
                                                <h6 class="mb-0 fw-bold text-muted"
                                                    title="Total de clases en el semestre">
                                                    {{ $docente['esperadas_total'] }}
                                                </h6>
                                            </div>
                                            <small class="text-muted mt-1" style="font-size: 0.7rem; line-height: 1;">
                                                <span class="d-block fw-bold text-dark">Hasta hoy / Total
                                                    semestral</span>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if ($docente['esperadas_hoy'] > 0)
                                            <div class="d-flex justify-content-center gap-2 mb-2 mt-1">
                                                <span class="badge bg-success" title="Presente"><i
                                                        class="bi bi-check-circle"></i>
                                                    {{ $docente['presentes'] }}</span>
                                                <span class="badge bg-danger" title="Falta"><i
                                                        class="bi bi-x-circle"></i> {{ $docente['faltas'] }}</span>
                                                <span class="badge bg-warning text-dark" title="Justificado"><i
                                                        class="bi bi-info-circle"></i>
                                                    {{ $docente['justificadas'] }}</span>
                                            </div>
                                            <div class="progress shadow-sm mx-auto" style="height: 6px; width: 80%;">
                                                <div class="progress-bar bg-{{ $docente['color'] }}"
                                                    style="width: {{ $docente['porcentaje'] }}%"></div>
                                            </div>
                                            <small class="fw-bold text-{{ $docente['color'] }} d-block mt-1">
                                                {{ $docente['porcentaje'] }}% cumplimiento
                                            </small>
                                            {{-- Por qué puede salir bajo sin tener faltas --}}
                                            @if (($docente['por_registrar'] ?? 0) > 0)
                                                <small class="d-block text-muted mt-1" style="font-size: 0.72rem;"
                                                    title="Clases que ya debieron darse y no tienen el registro del profesor. No son faltas: se capturan en Clases pendientes.">
                                                    <i class="bi bi-hourglass-split me-1"></i>{{ $docente['por_registrar'] }}
                                                    {{ $docente['por_registrar'] == 1 ? 'clase por registrar' : 'clases por registrar' }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted small">Sin clases esperadas a la fecha</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No hay datos de docentes para
                                        el periodo seleccionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. SECCIÓN INDEPENDIENTE PARA LA AUDITORÍA DE MATERIAS (BANNER) --}}
    <div class="col-12 mt-2">
        <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(to right, #ffffff, #f8f9fa);">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-3 mb-md-0">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-journal-check text-marca-green me-2 fs-4"></i> Auditoría Académica (Bitácora)
                    </h5>
                    <p class="text-muted small mb-0 mt-1">
                        Consulta el desglose detallado de asistencia, cumplimiento y pases de lista cruzados por materia
                        y grupo.
                    </p>
                </div>
                <div>
                    <a href="{{ route('admin.reportes.materias-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-sm btn-outline-secondary rounded-pill fw-bold shadow-sm px-4">
                        Ver bitácora <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
