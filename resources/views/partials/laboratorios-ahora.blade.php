{{--
    Tarjetas "Laboratorios en este momento": clase en curso, PCs en uso y PCs en
    mantenimiento. Cada tarjeta abre el monitor de ese laboratorio.

    La usan el inicio del encargado, el inicio del administrador y el Monitor de
    Laboratorios. Los datos salen de App\Support\EstadoDeLaboratorios::ahora().

    Recibe: $laboratorios y, opcionales, $columnaLab (clases de la columna) y
    $conBotonMonitor (muestra "Entrar al Monitor", como en el Monitor).
--}}
@php
    $columnaLab = $columnaLab ?? 'col-md-6 col-xl-3';
    $conBotonMonitor = $conBotonMonitor ?? false;
@endphp

@once
    <style>
        .tarjeta-lab {
            transition: all .2s ease;
        }

        .tarjeta-lab:hover {
            box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .12) !important;
            transform: translateY(-3px);
        }
    </style>
@endonce

<div class="row g-3 mb-4">
    @foreach ($laboratorios as $lab)
        @php
            $capacidad = max(1, (int) $lab->centro->capacidad);
            $porcentaje = round(($lab->ocupadas / $capacidad) * 100);
        @endphp
        <div class="{{ $columnaLab }}">
            <a href="{{ route('monitor.show', $lab->centro->id) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 tarjeta-lab">
                    <div class="card-body p-4 {{ $conBotonMonitor ? 'd-flex flex-column' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-marca-black mb-0"><i class="bi bi-pc-display text-marca-green me-1"></i>
                                {{ $lab->centro->nombre_centro }}</h6>
                            @if ($lab->clases->isNotEmpty())
                                <span class="badge bg-marca-green rounded-pill"><i class="bi bi-broadcast"></i> En clase</span>
                            @elseif ($lab->centro->permite_uso_libre)
                                <span class="badge bg-marca-yellow text-marca-black rounded-pill">Uso libre</span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill">Libre</span>
                            @endif
                        </div>

                        {{-- Clase en curso --}}
                        <div class="small text-muted mb-3" style="min-height: 2.6rem;">
                            @forelse ($lab->clases as $clase)
                                <div class="text-truncate">
                                    <strong class="text-dark">{{ $clase['horario']->materia->nombre_materia ?? 'Clase' }}</strong>
                                    · {{ trim(($clase['horario']->user->name ?? '') . ' ' . ($clase['horario']->user->apellido_paterno ?? '')) }}
                                    <span class="text-nowrap">(hasta {{ \Carbon\Carbon::parse($clase['horario']->hora_fin)->format('H:i') }})</span>
                                </div>
                            @empty
                                Sin clase en este momento
                            @endforelse
                        </div>

                        {{-- Ocupación --}}
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">PCs en uso</span>
                            <span class="fw-bold text-marca-black">{{ $lab->ocupadas }} / {{ $lab->centro->capacidad }}</span>
                        </div>
                        <div class="progress mb-2" style="height: 8px; border-radius: 10px;">
                            <div class="progress-bar bg-marca-green" style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <div class="small {{ $lab->mantenimiento > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                            <i class="bi bi-tools"></i>
                            {{ $lab->mantenimiento }} {{ $lab->mantenimiento == 1 ? 'PC en mantenimiento' : 'PCs en mantenimiento' }}
                        </div>

                        @if ($conBotonMonitor)
                            <div class="mt-auto pt-3 text-center border-top border-light">
                                <span class="fw-bold text-marca-green small">
                                    Entrar al Monitor <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
