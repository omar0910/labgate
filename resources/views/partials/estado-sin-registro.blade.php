{{--
    Estado de una clase que todavía no tiene la asistencia del profesor. Antes todas
    decían "Pendiente", y no se distinguía la que ya terminó sin registro de la que
    aún no empieza. Usa los mismos nombres que la Semana de clases.

    Recibe: $horarioSinRegistro (la clase) y $fechaSinRegistro (el día, AAAA-MM-DD).
--}}
@php
    $diaClase = \Carbon\Carbon::parse($fechaSinRegistro)->format('Y-m-d');
    $hoyMismo = \Carbon\Carbon::today()->format('Y-m-d');
    $horaAhora = \Carbon\Carbon::now()->format('H:i:s');

    if ($diaClase < $hoyMismo || ($diaClase === $hoyMismo && $horarioSinRegistro->hora_fin <= $horaAhora)) {
        $estadoSinRegistro = 'por_registrar';
    } elseif ($diaClase === $hoyMismo && $horarioSinRegistro->hora_inicio <= $horaAhora) {
        $estadoSinRegistro = 'en_curso';
    } else {
        $estadoSinRegistro = 'proxima';
    }
@endphp

@if ($estadoSinRegistro === 'por_registrar')
    <span class="text-dark" title="La clase ya terminó y falta registrar si el profesor la dio">
        <i class="bi bi-hourglass-split me-1"></i>{{ \App\Support\AgendaSemanal::ESTADOS['por_registrar'] }}
    </span>
@elseif ($estadoSinRegistro === 'en_curso')
    <span class="text-marca-green" title="La clase está en curso">
        <i class="bi bi-broadcast me-1"></i>{{ \App\Support\AgendaSemanal::ESTADOS['en_curso'] }}
    </span>
@else
    <span class="text-muted" title="La clase todavía no empieza">
        {{ \App\Support\AgendaSemanal::ESTADOS['proxima'] }}
    </span>
@endif
