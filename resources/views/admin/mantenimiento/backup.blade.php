@extends('layouts.admin')

@section('title', 'Respaldos del Sistema')

@section('content')

    {{-- 1. ENCABEZADO MODERNO (mismo patrón que el resto del panel) --}}
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-marca-black">
                <i class="bi bi-database-fill-gear text-marca-green me-2"></i> Respaldos de Base de Datos
            </h3>
            <p class="text-muted small mb-0 mt-1">Exporta la información del sistema o restaura un respaldo anterior.</p>
        </div>

        {{-- BOTÓN DE VOLVER ARRIBA --}}
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4"
                title="Volver al Inicio">
                <i class="bi bi-arrow-left me-1"></i> Volver al Inicio
            </a>
        </div>
    </div>

    {{-- 2. MENSAJES DE ALERTA --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- Errores del formulario (archivo o confirmación que faltan) --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- 3. LAS DOS OPERACIONES --}}
    <div class="row g-4">

        {{-- TARJETA DE EXPORTAR --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0 rounded-4 position-relative overflow-hidden">

                {{-- Franja de color superior --}}
                <div class="position-absolute top-0 start-0 w-100 bg-marca-green" style="height: 5px;"></div>

                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold text-marca-black mb-1">
                        <i class="bi bi-box-arrow-down text-marca-green me-2"></i> Exportar información
                    </h5>
                    <p class="text-muted small mb-4">
                        Genera un archivo <strong>.sql</strong> con toda la información actual del sistema
                        (alumnos, docentes, asistencias, horarios y configuraciones).
                    </p>

                    <div class="bg-light rounded-3 p-3 mb-4">
                        <small class="text-muted">
                            <i class="bi bi-info-circle text-marca-green me-1"></i>
                            Se recomienda realizar este proceso al menos una vez por semana.
                        </small>
                    </div>

                    <a href="{{ route('backup.exportar') }}"
                        class="btn btn-marca-green w-100 rounded-pill fw-bold shadow-sm py-2 mt-auto">
                        <i class="bi bi-download me-2"></i> Descargar respaldo actual
                    </a>
                </div>
            </div>
        </div>

        {{-- TARJETA DE IMPORTAR --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0 rounded-4 position-relative overflow-hidden">

                {{-- Franja de color superior: roja porque es la operación destructiva --}}
                <div class="position-absolute top-0 start-0 w-100 bg-danger" style="height: 5px;"></div>

                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold text-marca-black mb-1">
                        <i class="bi bi-box-arrow-in-up text-danger me-2"></i> Restaurar un respaldo
                    </h5>
                    <p class="text-muted small mb-4">
                        Devuelve el sistema a un punto anterior usando un archivo de respaldo.
                        <strong class="text-danger">Este proceso borra todos los datos actuales</strong>
                        y los reemplaza por los del archivo.
                    </p>

                    <form action="{{ route('backup.importar') }}" method="POST" enctype="multipart/form-data"
                        class="d-flex flex-column flex-grow-1">
                        @csrf
                        <div class="mb-3">
                            <label for="backup_file" class="form-label small fw-bold text-marca-black">
                                Archivo .sql
                            </label>
                            <input class="form-control" type="file" id="backup_file" name="backup_file" accept=".sql"
                                required>
                        </div>

                        {{-- Confirmación escrita: restaurar reemplaza TODA la base --}}
                        <div class="mb-3">
                            <label for="confirmacion" class="form-label small fw-bold text-marca-black">
                                Para confirmar, escribe <span class="text-danger">RESTAURAR</span>
                            </label>
                            <input class="form-control" type="text" id="confirmacion" name="confirmacion"
                                autocomplete="off" required placeholder="RESTAURAR">
                        </div>

                        <p class="small text-muted mb-4">
                            <i class="bi bi-shield-check text-marca-green me-1"></i>
                            Antes de restaurar, el sistema guarda automáticamente en el servidor una copia de los datos
                            actuales, por si hubiera que volver atrás.
                        </p>

                        <button type="submit" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-2 mt-auto"
                            onclick="return confirm('¿ESTÁS SEGURO? Esta acción reemplazará todos los datos actuales por los del archivo.')">
                            <i class="bi bi-upload me-2"></i> Iniciar restauración
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. NOTA INFORMATIVA --}}
    <div class="card shadow-sm border-0 rounded-4 mt-4 bg-white">
        <div class="card-body p-4">
            <h6 class="fw-bold text-marca-black mb-2">
                <i class="bi bi-shield-check text-marca-green me-2"></i> ¿Por qué es importante el respaldo?
            </h6>
            <p class="text-muted small mb-0">
                En caso de una falla en el servidor o pérdida accidental de datos en el Centro de Cómputo,
                estos archivos te permitirán recuperar la operatividad del sistema en minutos.
                Guarda los respaldos en un lugar seguro fuera del servidor.
            </p>
        </div>
    </div>

@endsection
