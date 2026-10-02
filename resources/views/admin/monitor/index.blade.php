@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Monitor de Laboratorios')

@section('content')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        /* Estilo de Tarjetas del Monitor */
        .hover-card {
            transition: all 0.3s ease;
            border: 2px solid transparent !important;
        }

        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 155, 77, 0.15) !important;
            border-color: var(--marca-green) !important;
        }

        /* Panel Sticky para la Bitácora */
        .sticky-panel {
            position: sticky;
            top: 2rem;
            /* Distancia desde el tope al hacer scroll */
        }

        /* Botón de acceso a Bitácora */
        .btn-acceso-bitacora {
            transition: all 0.3s ease;
            background-color: var(--marca-green);
            color: white;
        }

        .btn-acceso-bitacora:hover {
            background-color: var(--marca-dark-green);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 155, 77, 0.3) !important;
            color: white;
        }
    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO MODERNO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-sliders text-marca-green me-2"></i> Laboratorios y Bitácora de Clases
                </h3>
                <p class="text-muted small mb-0 mt-1">Control de laboratorios en tiempo real y bitácoras de asistencia.</p>
            </div>
        </div>

        {{-- DISEÑO DE PANTALLA DIVIDIDA (SIDE-BY-SIDE) --}}
        <div class="row g-4">

            {{-- LADO IZQUIERDO: MONITOR EN VIVO --}}
            <div class="col-lg-7 col-xl-8">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-display fs-4 text-marca-green me-2"></i>
                    <h5 class="fw-bold mb-0 text-dark">Monitor en Vivo</h5>
                </div>

                <div class="alert bg-light border text-muted small rounded-3 mb-4">
                    <i class="bi bi-info-circle-fill text-marca-green me-1"></i> Selecciona un laboratorio para gestionar el
                    <strong>uso libre</strong> y ver la ocupación actual.
                </div>

                {{-- Cada laboratorio con su ocupación de este momento (antes sólo la capacidad).
                     El mismo bloque del inicio del admin y del encargado. --}}
                @if ($laboratorios->isEmpty())
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-pc-display fs-1 text-muted opacity-25 d-block mb-3"></i>
                            <h6 class="fw-bold text-dark">Sin laboratorios</h6>
                            <p class="text-muted small mb-0">No hay laboratorios registrados en el sistema.</p>
                        </div>
                    </div>
                @else
                    @include('partials.laboratorios-ahora', [
                        'laboratorios' => $laboratorios,
                        'columnaLab' => 'col-md-6 col-xl-4',
                        'conBotonMonitor' => true,
                    ])
                @endif
            </div>

            {{-- LADO DERECHO: PUERTA DE ENLACE A BITÁCORA --}}
            <div class="col-lg-5 col-xl-4">
                <div class="sticky-panel">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-journal-check fs-4 text-marca-green me-2"></i>
                        <h5 class="fw-bold mb-0 text-dark">Bitácora de Clases</h5>
                    </div>

                    <div class="card shadow-sm border-0 rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5 text-center d-flex flex-column align-items-center justify-content-center"
                            style="min-height: 400px;">

                            <div class="mb-4 bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                style="width: 90px; height: 90px;">
                                <i class="bi bi-clipboard-check text-marca-green" style="font-size: 3rem;"></i>
                            </div>

                            <h4 class="fw-bold text-marca-black mb-3">Pase de lista y justificaciones</h4>
                            <p class="text-muted small mb-4 px-2">
                                Accede al módulo dedicado para buscar clases, pasar lista, registrar justificaciones y
                                llevar el control de equipos asignados a cada alumno.
                            </p>

                            {{-- Botón de Enlace (Por ahora con #) --}}
                            <a href="{{ route('admin.bitacora.index') }}"
                                class="btn btn-acceso-bitacora rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center fs-5">
                                Abrir la Bitácora <i class="bi bi-arrow-right-circle-fill ms-2"></i>
                            </a>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

@endsection
