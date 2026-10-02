@extends(Auth::user()->rol == 'Administrador' ? 'layouts.admin' : 'layouts.encargado')

@section('title', 'Bitácora de Clases')

@section('content')

    {{-- ESTILOS INSTITUCIONALES --}}
    <style>
        .shadow-hover:hover {
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05) !important;
            background-color: #f1f8f5;
            /* Un verde súper clarito al pasar el ratón */
            transition: all .2s ease;
        }

    </style>

    <div class="container-fluid pb-5">

        {{-- ENCABEZADO --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom border-2 border-marca-green">
            <div>
                <h3 class="mb-0 fw-bold text-marca-black"><i class="bi bi-journal-bookmark-fill text-marca-green me-2"></i>
                    Buscar una clase</h3>
                <p class="text-muted small mb-0 mt-1">
                    Busca una clase registrada para pasar lista.
                    @if ($semestreActivo)
                        Mostrando clases del semestre: <span
                            class="fw-bold text-marca-green">{{ $semestreActivo->nombre }}</span>
                    @endif
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="{{ route('admin.bitacora.pendientes') }}"
                    class="btn bg-marca-green text-white rounded-pill shadow-sm px-4 fw-bold">
                    <i class="bi bi-clipboard-check me-1"></i> Clases pendientes
                </a>
                <a href="{{ route('monitor.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        {{-- TARJETA DEL BUSCADOR --}}
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white">
            <div class="card-body p-4">
                <form action="{{ route('admin.bitacora.index') }}" method="GET" class="mb-0">
                    <div class="row align-items-end">
                        <div class="col-md-8 col-lg-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Buscar Clase por Materia,
                                Profesor o Grupo</label>
                            <div class="d-flex gap-2">
                                <div class="input-group shadow-sm rounded-3 overflow-hidden border" style="flex-grow: 1;">
                                    <span class="input-group-text bg-white border-0 text-marca-green">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-0 search-input ps-0"
                                        placeholder="Ej: Redes, Juan, 1SM..." value="{{ request('search') }}">
                                    <button type="submit" class="btn bg-marca-green text-white fw-bold px-4 border-0">
                                        Buscar
                                    </button>
                                </div>

                                @if (request('search'))
                                    <a href="{{ route('admin.bitacora.index') }}"
                                        class="btn btn-outline-danger shadow-sm rounded-3 px-3 d-flex align-items-center"
                                        title="Limpiar filtros">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- TABLA DE RESULTADOS --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 border-0">Materia</th>
                            <th class="border-0">Profesor</th>
                            <th class="border-0">Grupo</th>
                            <th class="border-0">Horario y Laboratorio</th>
                            <th class="text-center pe-4 border-0">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Una fila por asignatura (materia + grupo + profesor) con todas sus sesiones --}}
                        @forelse ($asignaturas as $asignatura)
                            <tr class="shadow-hover border-bottom border-light">

                                {{-- Materia --}}
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                        {{ $asignatura->materia->nombre_materia ?? 'N/A' }}
                                    </div>
                                    <small class="text-muted"><i
                                            class="bi bi-upc-scan me-1"></i>{{ $asignatura->materia->clave ?? 'S/C' }}</small>
                                </td>

                                {{-- Profesor (Con el formato inteligente que hicimos) --}}
                                <td>
                                    @php
                                        $nombreProf = $asignatura->user->name ?? '';
                                        $apeProf = $asignatura->user->apellido_paterno ?? '';
                                        $inicial = $nombreProf ? mb_substr($nombreProf, 0, 1, 'UTF-8') . '. ' : '';
                                        $apeFormateado = mb_convert_case(
                                            mb_strtolower($apeProf, 'UTF-8'),
                                            MB_CASE_TITLE,
                                            'UTF-8',
                                        );
                                        $profesorFinal = trim($inicial . $apeFormateado);
                                    @endphp
                                    <span class="text-secondary fw-semibold">
                                        <i class="bi bi-person-badge me-1"></i>{{ $profesorFinal ?: 'Sin Asignar' }}
                                    </span>
                                </td>

                                {{-- Grupo --}}
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        {{ $asignatura->grupo->nombre_grupo ?? 'N/A' }}
                                    </span>
                                </td>

                                {{-- Horario y Centro: una línea por sesión --}}
                                <td>
                                    @php $variosLabs = $asignatura->laboratorios->count() > 1; @endphp
                                    @foreach ($asignatura->sesiones as $sesion)
                                        <div class="fw-bold text-marca-green small">
                                            {{ $sesion->etiquetaDeSesion() }}
                                            @if ($variosLabs)
                                                <span class="text-muted fw-normal">· {{ $sesion->centroComputo->nombre_centro ?? '' }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @unless ($variosLabs)
                                        <small class="text-muted"><i
                                                class="bi bi-pc-display me-1"></i>{{ $asignatura->laboratorios->first() ?? 'N/A' }}</small>
                                    @endunless
                                </td>

                                {{-- Acción --}}
                                <td class="text-center pe-4">
                                    <a href="{{ route('admin.bitacora.show', $asignatura->id) }}"
                                        class="btn btn-marca-green btn-sm rounded-pill shadow-sm fw-bold px-3">
                                        Abrir clase <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted border-0">
                                    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                    <span class="d-block fw-bold text-dark fs-5">No se encontraron clases</span>
                                    <small>Intenta buscar con otro término o revisa el módulo de horarios.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if ($asignaturas->hasPages())
                <div class="card-footer bg-white border-top-0 pt-4 pb-3 px-4">
                    {{ $asignaturas->links() }}
                </div>
            @endif
        </div>

    </div>

@endsection
