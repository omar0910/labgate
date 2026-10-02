@extends('layouts.admin')
@section('title', 'Reportes Generales')

@section('content')

    {{-- ESTILOS INSTITUCIONALES PARA LAS PESTAÑAS --}}
    <style>
        /* Diseño de los botones de pestañas */
        .nav-pills .nav-link {
            color: #495057 !important;
            /* Gris oscuro para forzar que se vea el texto inactivo */
            background-color: transparent !important;
            font-weight: 600;
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            transition: all 0.2s ease;
            margin-right: 0.5rem;
            border: 1px solid transparent;
        }

        .nav-pills .nav-link:hover:not(.active) {
            background-color: #f8f9fa !important;
            color: var(--marca-green) !important;
            border-color: #dee2e6 !important;
        }

        .nav-pills .nav-link.active,
        .nav-pills .show>.nav-link {
            background-color: var(--marca-green) !important;
            color: white !important;
            /* Letra blanca SOLO cuando la pestaña está activa */
            box-shadow: 0 4px 6px rgba(0, 155, 77, 0.2);
        }
    </style>

    {{-- Quitamos el pb-5 para no generar espacios vacíos al final --}}
    <div class="container-fluid">

        {{-- 1. ENCABEZADO Y FILTRO GLOBAL --}}
        <div
            class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom border-2 border-marca-green">
            {{-- El título ya lo pone el layout; aquí sólo va el contexto. --}}
            <div>
                <p class="text-muted small mb-0">
                    Estado del centro de cómputo por periodo: ocupación, cumplimiento de clases y uso de equipos.
                </p>
            </div>

            {{-- Buscador Global por Semestre --}}
            <div class="mt-3 mt-lg-0" style="min-width: 300px;">
                <form action="{{ route('admin.reportes.index') }}" method="GET" id="filtroGlobalForm">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden"
                        style="border: 1px solid #ced4da; background: white;">
                        <span class="input-group-text bg-white border-0 text-marca-green ps-3">
                            <i class="bi bi-funnel-fill"></i>
                        </span>
                        <select name="semestre_id" class="form-select border-0 fw-bold text-dark py-2"
                            onchange="document.getElementById('filtroGlobalForm').submit()">
                            <option value="">Selecciona un periodo...</option>
                            @foreach ($semestres as $sem)
                                <option value="{{ $sem->id }}"
                                    {{ $semestreSeleccionadoId == $sem->id ? 'selected' : '' }}>
                                    {{ $sem->nombre }} {!! $sem->es_activo ? '&#9733; (Actual)' : '' !!}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        {{-- 2. MENÚ DE NAVEGACIÓN DE REPORTES (PILLS) --}}
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
            <div class="card-body p-3">
                <ul class="nav nav-pills flex-column flex-md-row" id="reportesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active w-100 mb-2 mb-md-0" id="dashboard-tab" data-bs-toggle="pill"
                            data-bs-target="#dashboard" type="button" role="tab">
                            <i class="bi bi-grid-1x2-fill me-1"></i> Resumen General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 mb-2 mb-md-0" id="docentes-tab" data-bs-toggle="pill"
                            data-bs-target="#docentes" type="button" role="tab">
                            <i class="bi bi-person-badge-fill me-1"></i> Docentes y Materias
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 mb-2 mb-md-0" id="alumnos-tab" data-bs-toggle="pill"
                            data-bs-target="#alumnos" type="button" role="tab">
                            <i class="bi bi-mortarboard-fill me-1"></i> Alumnos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 mb-2 mb-md-0" id="infraestructura-tab" data-bs-toggle="pill"
                            data-bs-target="#infraestructura" type="button" role="tab">
                            <i class="bi bi-pc-display me-1"></i> Laboratorios y Uso Libre
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        {{-- 3. CONTENEDORES DE LAS PESTAÑAS --}}
        {{-- Quitamos márgenes extra del tab-content --}}
        <div class="tab-content" id="reportesTabsContent">

            {{-- Pestaña: Resumen --}}
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel" tabindex="0">
                @include('admin.reportes.partials.resumen')
            </div>

            {{-- Pestaña: Docentes --}}
            <div class="tab-pane fade" id="docentes" role="tabpanel" tabindex="0">
                @include('admin.reportes.partials.docentes')
            </div>

            {{-- Pestaña: Alumnos --}}
            <div class="tab-pane fade" id="alumnos" role="tabpanel" tabindex="0">
                @include('admin.reportes.partials.alumnos')
            </div>

            {{-- Pestaña: Infraestructura y Uso Libre --}}
            <div class="tab-pane fade" id="infraestructura" role="tabpanel" tabindex="0">
                @include('admin.reportes.partials.laboratorios')
            </div>

        </div>

    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Le damos un ligerísimo retraso (150ms) para asegurar que Bootstrap ya cargó en el DOM
            setTimeout(function() {
                var hash = window.location.hash;

                if (hash) {
                    // Buscamos el botón exacto que controla la pestaña
                    var tabBoton = document.querySelector('button[data-bs-target="' + hash + '"]');

                    if (tabBoton) {
                        // Forzamos el clic en el botón (es más seguro que usar la API de Bootstrap a veces)
                        tabBoton.click();

                        // Subimos la vista arriba suavemente por si el navegador intentó bajar al div oculto
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                }
            }, 150);

            // Esto actualiza la URL arriba cuando cambias de pestaña manualmente
            var botonesPestañas = document.querySelectorAll('button[data-bs-toggle="pill"]');
            botonesPestañas.forEach(function(boton) {
                boton.addEventListener('shown.bs.tab', function(event) {
                    var destino = event.target.getAttribute('data-bs-target');
                    if (destino) {
                        history.replaceState(null, null, destino);
                    }
                });
            });
        });
    </script>
@endsection
