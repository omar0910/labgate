<div class="row g-4">

    {{-- 1. ENCABEZADO INSTITUCIONAL --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mb-2">
            <div
                class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pc-display text-marca-green me-2"></i> Reporte de Laboratorios y Uso Libre
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Estadísticas de impacto, sesiones y desgaste de hardware.</p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="{{ route('admin.reportes.laboratorios.pdf', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">
                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                    </a>
                    <a href="{{ route('admin.reportes.laboratorios.excel', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-success rounded-pill fw-bold shadow-sm px-4" style="background-color: #198754;">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. RESUMEN DE IMPACTO POR LABORATORIO (ESTILO LIMPIO) --}}
    @foreach ($reporteLaboratorios as $lab)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-marca-green text-white border-0 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pc-display me-2"></i> {{ $lab['nombre'] }}</h6>
                        <span class="badge bg-white text-dark rounded-pill">Activo</span>
                    </div>
                </div>

                <div class="card-body p-4 flex-grow-1 d-flex flex-column">
                    <div class="row text-center mb-3">
                        <div class="col-6 border-end">
                            <h4 class="fw-black mb-0">{{ $lab['sesiones'] }}</h4>
                            <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Asist. a Clase</small>
                        </div>
                        <div class="col-6">
                            <h4 class="fw-black mb-0 text-warning">{{ $lab['uso_libre'] }}</h4>
                            <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Uso Libre</small>
                        </div>
                    </div>

                    <hr class="my-0 opacity-25 mb-3">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small"><i class="bi bi-clock-history"></i> Hora Pico:</span>
                        <span class="fw-bold text-dark small">{{ $lab['hora_pico'] }}</span>
                    </div>

                    {{-- Asistencias a clase con la laptop del alumno (no ocupan PC) --}}
                    <div class="d-flex justify-content-between align-items-center mb-3"
                        title="Asistencias a clase en las que el alumno trabajó con su propia computadora">
                        <span class="text-muted small"><i class="bi bi-laptop"></i> Con equipo personal:</span>
                        <span class="fw-bold text-dark small">
                            {{ $lab['equipo_personal'] ?? 0 }}
                            @if (($lab['sesiones'] ?? 0) > 0)
                                <span class="text-muted fw-normal">({{ round((($lab['equipo_personal'] ?? 0) / $lab['sesiones']) * 100) }}% de la clase)</span>
                            @endif
                        </span>
                    </div>

                    @php
                        $porcentajeUsoLibre =
                            $lab['total_impacto'] > 0 ? ($lab['uso_libre'] / $lab['total_impacto']) * 100 : 0;
                    @endphp

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Demanda por Uso Libre</small>
                            <small class="fw-bold">{{ round($porcentajeUsoLibre) }}%</small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-warning" role="progressbar"
                                style="width: {{ $porcentajeUsoLibre }}%"></div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <small class="text-muted fw-bold d-block mb-2"><i class="bi bi-cpu text-secondary"></i> Top
                            Máquinas Histórico:</small>
                        <div class="d-flex gap-2 flex-wrap">
                            @forelse($lab['top_maquinas'] ?? [] as $maquina)
                                <span
                                    class="badge bg-light text-dark border border-secondary border-opacity-25 shadow-sm"
                                    style="font-size: 0.75rem;">
                                    PC #{{ $maquina->numero_maquina }} <span
                                        class="text-marca-green ms-1">({{ $maquina->total_usos }})</span>
                                </span>
                            @empty
                                <span class="text-muted small">Sin datos</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- 7. BITÁCORA DETALLADA DE USO LIBRE --}}
    <div class="col-12 mt-4">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap"
                style="border-radius: 1rem;">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-journal-text text-marca-green me-2"></i> Reporte de Bitácora Completo de Uso Libre
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Historial de todos los estudiantes que ingresaron
                        a uso individual.</p>
                </div>
                <a href="{{ route('admin.reportes.uso-libre-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                    class="btn btn-sm btn-outline-secondary rounded-pill fw-bold shadow-sm px-4">
                    Ver bitácora <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- 3. ACCESO RÁPIDO A GESTIÓN DE USO LIBRE 
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 bg-light">
            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <div class="bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="bi bi-search text-warning fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Buscador Avanzado de Uso Libre</h5>
                        <p class="text-muted mb-0 small">Filtros detallados, búsqueda por alumno y gestión de registros.
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.reportes.uso-libre', ['origen' => 'reportes', 'pestana' => 'infraestructura', 'semestre_id' => $semestreSeleccionadoId]) }}"
                    class="btn btn-warning rounded-pill fw-bold px-4 shadow-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Abrir Historial Completo
                </a>
            </div>
        </div>
    </div>
    --}}

    {{-- 4. SECCIÓN UNIFICADA: ANÁLISIS ACADÉMICO POR CARRERA --}}
    <div class="col-12 mt-4">
        <div class="card border-0 shadow-sm rounded-4 bg-light border border-secondary border-opacity-25">
            <div
                class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-mortarboard-fill text-marca-green me-2"></i>
                        Alcance y Demanda Académica por Carrera</h5>
                    <p class="text-muted small mt-1 mb-0">Análisis basado en estudiantes únicos que utilizaron la
                        infraestructura.</p>
                </div>
                <a href="{{ route('admin.reportes.carreras-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                    class="btn btn-sm btn-outline-secondary rounded-pill mt-3 mt-md-0 fw-bold shadow-sm">
                    Ver reporte completo <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <div class="row g-4">
                    {{-- Lado Izquierdo: Alcance Global --}}
                    <div class="col-lg-6">
                        <div class="bg-white rounded-4 shadow-sm border p-4 h-100">
                            <h6 class="fw-bold text-marca-green mb-3 border-bottom pb-2">Impacto Institucional (Global)
                            </h6>
                            @forelse($reporteCarreras as $index => $dato)
                                @php $color = $index == 0 ? 'marca-green' : ($index == 1 ? 'warning' : 'primary'); @endphp
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-end mb-1">
                                        <span
                                            class="fw-bold text-dark small text-uppercase">{{ $dato['carrera'] }}</span>
                                        <span class="fw-black">{{ $dato['porcentaje'] }}% <small
                                                class="text-muted fw-normal">({{ $dato['cantidad'] }})</small></span>
                                    </div>
                                    <div class="progress shadow-sm" style="height: 8px;">
                                        <div class="progress-bar bg-{{ $color }} progress-bar-striped"
                                            style="width: {{ $dato['porcentaje'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted">No hay datos globales.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Lado Derecho: Desglose por Laboratorio --}}
                    <div class="col-lg-6">
                        <div class="bg-white rounded-4 shadow-sm border p-4 h-100">
                            <h6 class="fw-bold text-marca-green mb-3 border-bottom pb-2">Preferencia por Laboratorio (Top
                                3)</h6>
                            <div class="row g-3">
                                @foreach ($reporteLaboratorios as $lab)
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-3 bg-light border border-opacity-50">
                                            <small class="fw-bold text-dark d-block mb-2 text-uppercase"
                                                style="font-size: 0.7rem;">{{ $lab['nombre'] }}</small>
                                            @forelse($lab['top_carreras'] as $tc)
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between small mb-1">
                                                        <span class="text-truncate" style="max-width: 55%;"
                                                            title="{{ $tc['carrera'] }}">{{ $tc['carrera'] }}</span>
                                                        <span class="fw-bold text-marca-green">
                                                            {{ $tc['porcentaje'] }}% <span
                                                                class="text-muted fw-normal"
                                                                style="font-size: 0.7rem;">({{ $tc['cantidad'] }})</span>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 3px;">
                                                        <div class="progress-bar bg-marca-green"
                                                            style="width: {{ $tc['porcentaje'] }}%"></div>
                                                    </div>
                                                </div>
                                            @empty
                                                <small class="text-muted italic">Sin registros únicos</small>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. TOP 10 HISTÓRICO DEL SEMESTRE --}}
    <div class="col-12 mt-4 mb-2">
        <div class="card border-0 shadow-sm rounded-4 bg-light border border-secondary border-opacity-25">
            {{-- HEADER MODIFICADO CON EL BOTÓN --}}
            <div
                class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-award-fill text-warning me-2"></i> Récord
                        Histórico de Equipos</h5>
                    <p class="text-muted small mt-1 mb-0">Las 10 computadoras con mayor carga de trabajo en el
                        semestre.</p>
                </div>
                <a href="{{ route('admin.reportes.record-historico', ['semestre_id' => $semestreSeleccionadoId]) }}"
                    class="btn btn-sm btn-outline-secondary rounded-pill mt-3 mt-md-0 fw-bold shadow-sm">
                    Ver reporte completo <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <div class="table-responsive bg-white rounded-3 shadow-sm border">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase text-muted">
                                <th class="ps-4">Equipo / Ubicación</th>
                                <th class="text-center">Total Sesiones</th>
                                <th class="text-center pe-4">Horas de Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topMaquinasHistorico as $hist)
                                @php
                                    $horas = floor($hist->total_minutos / 60);
                                    $minutos = $hist->total_minutos % 60;
                                @endphp
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark">PC #{{ $hist->numero_maquina }}</div>
                                        <div class="small text-muted">{{ $hist->centroComputo->nombre_centro }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge bg-success bg-opacity-10 text-marca-green border border-marca-green rounded-pill px-3 py-2 fs-6">{{ $hist->total_usos }}</span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="fw-bold text-dark fs-5"><i
                                                class="bi bi-clock-fill text-warning me-1"></i>{{ $horas }}h
                                            {{ $minutos }}m</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 6. TABLA DE SALUD DEL HARDWARE CON MANTENIMIENTO MASIVO --}}
    <div class="col-12 mt-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div
                class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-activity text-marca-green me-2"></i> Registro
                        Físico de Hardware</h5>
                    <p class="text-muted small mt-1 mb-0">Monitoreo de usos por equipo y gestión de mantenimiento.</p>
                </div>
                <div class="d-flex align-items-center mt-3 mt-md-0 gap-2">
                    <a href="{{ route('admin.reportes.hardware-detalle', ['semestre_id' => $semestreSeleccionadoId]) }}"
                        class="btn btn-sm btn-outline-secondary rounded-pill fw-bold shadow-sm">
                        Ver reporte completo <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-marca-green rounded-pill fw-bold px-3 shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#modalMantenimientoMasivo">
                        <i class="bi bi-tools me-1"></i> Mtto. Masivo
                    </button>
                </div>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="small text-uppercase text-muted">
                                <th class="ps-3 border-0 rounded-start" style="width: 40%;">Equipo / Ubicación</th>
                                <th class="text-center border-0" style="width: 30%;">Odómetros de Uso</th>
                                <th class="text-center border-0 rounded-end" style="width: 30%;">Estado Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equiposDesgaste as $equipo)
                                <tr class="border-bottom">
                                    {{-- COLUMNA 1: INFO DEL EQUIPO --}}
                                    <td class="ps-3 py-3">
                                        <span class="fw-bold text-dark fs-6">PC #{{ $equipo->numero_maquina }}</span>
                                        <div class="small text-muted mb-1"><i
                                                class="bi bi-geo-alt-fill opacity-50 me-1"></i>{{ $equipo->centroComputo->nombre_centro }}
                                        </div>
                                        @if ($equipo->ultimo_mantenimiento)
                                            <div class="small text-marca-green" style="font-size: 0.70rem;">
                                                <i class="bi bi-calendar-check"></i> Últ. mtto:
                                                {{ \Carbon\Carbon::parse($equipo->ultimo_mantenimiento)->format('d/m/Y') }}
                                            </div>
                                        @else
                                            <div class="small text-muted opacity-50" style="font-size: 0.70rem;">Sin
                                                registro de mtto.</div>
                                        @endif
                                    </td>

                                    {{-- COLUMNA 2: ODÓMETROS (VIAJE E HISTÓRICO) --}}
                                    <td class="text-center py-3">
                                        <div class="fw-bold text-dark fs-5">{{ $equipo->usos_acumulados }}</div>
                                        <div class="small text-muted text-uppercase mb-2" style="font-size: 0.65rem;">
                                            Usos recientes</div>

                                        <div class="text-muted bg-light rounded-pill d-inline-block px-3 py-1 border"
                                            style="font-size: 0.75rem;">
                                            <i class="bi bi-speedometer2 me-1 opacity-75"></i> Histórico:
                                            <strong>{{ $equipo->usos_historicos }}</strong>
                                        </div>
                                    </td>

                                    {{-- COLUMNA 3: ESTADO REAL --}}
                                    <td class="text-center">
                                        @if (in_array(mb_strtolower($equipo->estado ?? ''), ['mantenimiento', 'en mantenimiento']))
                                            <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold shadow-sm"><i
                                                    class="bi bi-tools me-1"></i> Mantenimiento</span>
                                        @elseif (in_array(mb_strtolower($equipo->estado ?? ''), ['disponible', 'activo', 'optimo', 'óptimo']))
                                            <span
                                                class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2 fw-bold"><i
                                                    class="bi bi-check-circle-fill me-1"></i> Disponible</span>
                                        @else
                                            <span
                                                class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-3 py-2 text-capitalize fw-bold">{{ $equipo->estado }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="bi bi-pc-display fs-1 text-secondary opacity-25 d-block mb-3"></i>
                                        No hay equipos registrados en el sistema.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL DE MANTENIMIENTO SELECTIVO --}}
    <div class="modal fade" id="modalMantenimientoMasivo" tabindex="-1" aria-labelledby="modalMantenimientoLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="modalMantenimientoLabel">
                        <i class="bi bi-tools text-marca-green me-2"></i> Registrar Mantenimiento de Equipos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.equipos.mantenimiento-masivo') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted small mb-4">Selecciona el laboratorio y marca las computadoras a las que
                            se les realizó mantenimiento. Su contador de "Usos Acumulados" volverá a cero (0).</p>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-dark">1. Selecciona el Laboratorio:</label>
                            <select id="selectLaboratorioMtto"
                                class="form-select border-secondary border-opacity-25 shadow-sm rounded-3">
                                <option value="" selected disabled>Elige un laboratorio...</option>
                                @foreach ($centros as $centro)
                                    <option value="lab_{{ $centro->id }}">{{ $centro->nombre_centro }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label
                                class="form-label fw-bold small text-dark d-flex justify-content-between align-items-center">
                                <span>2. Selecciona las Computadoras:</span>
                                <button type="button"
                                    class="btn btn-sm btn-link text-marca-green p-0 text-decoration-none fw-bold"
                                    id="btnMarcarTodas" style="display: none;">Marcar todas</button>
                            </label>

                            <div id="contenedorPcs"
                                class="border border-secondary border-opacity-25 rounded-3 p-3 bg-light shadow-sm overflow-auto"
                                style="min-height: 180px; max-height: 300px;">
                                <div class="text-center text-muted small mt-4" id="placeholderPcs">
                                    <i class="bi bi-pc-display fs-2 opacity-25 d-block mb-2"></i>
                                    Selecciona un laboratorio arriba para ver sus computadoras.
                                </div>

                                @foreach ($centros as $centro)
                                    <div class="row g-3 lista-pcs" id="lab_{{ $centro->id }}"
                                        style="display: none;">
                                        @forelse($centro->equipos as $equipo)
                                            <div class="col-6 col-md-4 col-lg-3">
                                                <div
                                                    class="form-check custom-checkbox-card bg-white border rounded-3 p-2 text-center h-100 d-flex flex-column justify-content-center position-relative">
                                                    <input class="form-check-input pc-checkbox position-absolute"
                                                        type="checkbox" name="equipos[]" value="{{ $equipo->id }}"
                                                        id="pc_{{ $equipo->id }}" style="top: 10px; left: 15px;">
                                                    <label
                                                        class="form-check-label w-100 fw-bold small mt-1 stretched-link cursor-pointer"
                                                        for="pc_{{ $equipo->id }}" style="cursor: pointer;">
                                                        PC #{{ $equipo->numero_maquina }}
                                                        <div class="text-muted fw-normal" style="font-size: 0.70rem;">
                                                            {{ $equipo->usos_acumulados }} usos</div>
                                                    </label>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12 text-center text-muted small py-3">No hay equipos
                                                registrados en este laboratorio.</div>
                                        @endforelse
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-marca-green rounded-pill px-4 fw-bold shadow-sm"
                            id="btnConfirmarMtto" disabled>Confirmar Acción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .custom-checkbox-card {
            transition: all 0.2s ease-in-out;
        }

        .custom-checkbox-card:hover {
            border-color: var(--marca-green) !important;
            background-color: #f8fff9 !important;
        }

        /* Efecto cuando el checkbox está seleccionado */
        .form-check-input:checked~label {
            color: var(--marca-green) !important;
        }

        .form-check-input:checked {
            background-color: var(--marca-green);
            border-color: var(--marca-green);
        }

        .custom-checkbox-card:has(.form-check-input:checked) {
            border: 2px solid var(--marca-green) !important;
            background-color: #e5f5ed !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectLab = document.getElementById('selectLaboratorioMtto');
            const placeholder = document.getElementById('placeholderPcs');
            const listasPcs = document.querySelectorAll('.lista-pcs');
            const btnMarcar = document.getElementById('btnMarcarTodas');
            const btnConfirmar = document.getElementById('btnConfirmarMtto');
            const allCheckboxes = document.querySelectorAll('.pc-checkbox');

            // Función para habilitar o deshabilitar el botón de Confirmar
            function checkSubmitButton() {
                const checkedCount = document.querySelectorAll('.pc-checkbox:checked').length;
                btnConfirmar.disabled = checkedCount === 0;
            }

            // Escuchar cambios en cualquier checkbox
            allCheckboxes.forEach(chk => chk.addEventListener('change', checkSubmitButton));

            // Cuando cambian el laboratorio en el Select
            selectLab.addEventListener('change', function() {
                // Ocultar todas las listas de PCs
                listasPcs.forEach(lista => {
                    lista.style.display = 'none';
                    // Desmarcar las PCs del lab anterior para evitar que se envíen por error
                    lista.querySelectorAll('input').forEach(chk => chk.checked = false);
                });

                placeholder.style.display = 'none';
                btnMarcar.style.display = 'block';
                btnMarcar.textContent = 'Marcar todas';

                // Mostrar la lista del laboratorio seleccionado
                const labId = this.value;
                const listaActiva = document.getElementById(labId);
                if (listaActiva) {
                    listaActiva.style.display = 'flex';
                }
                checkSubmitButton();
            });

            // Botón de "Marcar todas / Desmarcar todas"
            btnMarcar.addEventListener('click', function() {
                const labId = selectLab.value;
                if (!labId) return;

                const listaActiva = document.getElementById(labId);
                const checkboxes = listaActiva.querySelectorAll('.pc-checkbox');

                const estanTodasMarcadas = Array.from(checkboxes).every(chk => chk.checked);

                checkboxes.forEach(chk => chk.checked = !estanTodasMarcadas);
                this.textContent = estanTodasMarcadas ? 'Marcar todas' : 'Desmarcar todas';

                checkSubmitButton();
            });
        });
    </script>
</div>
