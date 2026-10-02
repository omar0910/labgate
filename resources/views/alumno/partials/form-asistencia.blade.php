{{--
    Formulario para registrar la asistencia a una clase: con la PC detectada por
    el script, con el número que escriba o con su equipo personal.

    Lo usa el panel del alumno en dos casos: la clase abierta para registrarse
    ('activa') y la asistencia que dejó el pase de lista sin PC ('sin_pc').
    Recibe: $horario y $equipoDetectado.
--}}
@php
    // ¿Entró desde una PC del laboratorio de esta clase?
    $enEsteLab = $equipoDetectado && $equipoDetectado['centro'] === (int) $horario->centro_computo_id;
@endphp

@if ($equipoDetectado && ! $enEsteLab)
    {{-- Está en una PC de OTRO laboratorio: desde ahí no se registra --}}
    <div class="alert alert-warning py-2 mb-0 small text-center border-0 text-dark">
        <i class="bi bi-geo-alt-fill"></i>
        Estás en una PC de <strong>{{ $equipoDetectado['laboratorio']->nombre_centro }}</strong>.
        Esta clase es en <strong>{{ $horario->centroComputo->nombre_centro }}</strong>:
        regístrate desde una computadora de ese laboratorio.
    </div>
@else
    <form action="{{ route('alumno.marcar-asistencia') }}" method="POST"
        class="d-flex flex-column gap-2">
        @csrf
        <input type="hidden" name="horario_id" value="{{ $horario->id }}">

        @if ($enEsteLab)
            {{-- La PC se detectó sola: no se escribe ni se cambia --}}
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light fw-bold">N° PC:</span>
                <input type="text" class="form-control fw-bold text-marca-green"
                    value="{{ $equipoDetectado['maquina'] }}" readonly
                    title="Detectada automáticamente">
                <button type="submit" class="btn btn-marca-green fw-bold px-3">Entrar</button>
            </div>
            <small class="text-muted">
                <i class="bi bi-pc-display-horizontal text-marca-green"></i>
                Estás en la PC #{{ $equipoDetectado['maquina'] }} de
                {{ $equipoDetectado['laboratorio']->nombre_centro }}.
            </small>
        @else
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light fw-bold">N° PC:</span>
                <input type="number" name="numero_maquina" class="form-control campo-pc"
                    required min="1" max="{{ $horario->centroComputo->capacidad ?? 100 }}"
                    placeholder="Ej: 5">
                {{-- Botón Entrar en Verde institucional --}}
                <button type="submit" class="btn btn-marca-green fw-bold px-3">Entrar</button>
            </div>
            {{-- Para quien trabaja en su laptop: registra sin número de PC --}}
            <div class="form-check form-switch small mb-0">
                <input class="form-check-input interruptor-personal" type="checkbox"
                    role="switch" name="equipo_personal" value="1"
                    id="personal_{{ $horario->id }}">
                <label class="form-check-label" for="personal_{{ $horario->id }}">
                    <i class="bi bi-laptop"></i> Uso mi equipo personal (laptop)
                </label>
            </div>
        @endif
    </form>
@endif
