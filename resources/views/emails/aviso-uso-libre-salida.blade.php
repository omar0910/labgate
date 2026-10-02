@php
    $lab     = optional($sesion->centroComputo)->nombre_centro ?? 'Laboratorio';
    $entrada = \Carbon\Carbon::parse($sesion->fecha_hora_registro);
    $salida  = \Carbon\Carbon::parse($sesion->fecha_hora_salida);
    $fecha   = $entrada->locale('es')->translatedFormat('l d \d\e F \d\e Y');
    $fecha   = mb_strtoupper(mb_substr($fecha, 0, 1)) . mb_substr($fecha, 1);

    // Duración en horas y minutos, redactada de forma legible.
    $minutosTotales = max(0, $entrada->diffInMinutes($salida));
    $horas          = intdiv($minutosTotales, 60);
    $minutos        = $minutosTotales % 60;

    if ($horas > 0 && $minutos > 0) {
        $duracion = $horas . ($horas == 1 ? ' hora ' : ' horas ') . $minutos . ($minutos == 1 ? ' minuto' : ' minutos');
    } elseif ($horas > 0) {
        $duracion = $horas . ($horas == 1 ? ' hora' : ' horas');
    } else {
        $duracion = $minutos . ($minutos == 1 ? ' minuto' : ' minutos');
    }
@endphp

@extends('emails.partials.base', ['titulo' => 'Sesión de Uso Libre terminada', 'color' => '#1a1a1a'])

@section('contenido')

    <p style="margin:0 0 6px; font-size:15px;">
        Hola <strong>{{ $alumno->nombre_completo }}</strong>,
    </p>

    <p style="margin:0 0 22px; font-size:15px; color:#555555; line-height:1.6;">
        Tu sesión de <strong>uso libre</strong> quedó cerrada correctamente. Este es el
        resumen de tu visita:
    </p>

    {{-- El tiempo total, que es el dato que más interesa --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-bottom:22px;">
        <tr>
            <td align="center"
                style="background-color:#009B4D; border-radius:8px; padding:20px 12px;">
                <div
                    style="color:#ffffff; font-size:12px; text-transform:uppercase; letter-spacing:1px; opacity:0.85;">
                    Tiempo de uso
                </div>
                <div style="color:#ffffff; font-size:26px; font-weight:bold; margin-top:4px;">
                    {{ $duracion }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Entrada y salida, lado a lado --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-bottom:22px;">
        <tr>
            <td width="50%" align="center"
                style="background-color:#f4f6f9; border-radius:8px 0 0 8px; padding:16px 12px; border-right:2px solid #ffffff;">
                <div style="color:#777777; font-size:12px; text-transform:uppercase; letter-spacing:1px;">
                    Entrada
                </div>
                <div style="color:#1a1a1a; font-size:22px; font-weight:bold; margin-top:4px;">
                    {{ $entrada->format('H:i') }}
                </div>
            </td>
            <td width="50%" align="center"
                style="background-color:#f4f6f9; border-radius:0 8px 8px 0; padding:16px 12px;">
                <div style="color:#777777; font-size:12px; text-transform:uppercase; letter-spacing:1px;">
                    Salida
                </div>
                <div style="color:#1a1a1a; font-size:22px; font-weight:bold; margin-top:4px;">
                    {{ $salida->format('H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="background-color:#f4f6f9; border-radius:8px; font-size:14px;">
        <tr>
            <td style="padding:16px 18px 6px; color:#777777; width:38%;">Laboratorio</td>
            <td style="padding:16px 18px 6px; color:#1a1a1a; font-weight:bold;">{{ $lab }}</td>
        </tr>
        <tr>
            <td style="padding:6px 18px; color:#777777;">Equipo</td>
            <td style="padding:6px 18px; color:#1a1a1a;">PC #{{ $sesion->numero_maquina }}</td>
        </tr>
        <tr>
            <td style="padding:6px 18px; color:#777777;">Fecha</td>
            <td style="padding:6px 18px; color:#1a1a1a;">{{ $fecha }}</td>
        </tr>
        <tr>
            <td style="padding:6px 18px 16px; color:#777777;">Matrícula</td>
            <td style="padding:6px 18px 16px; color:#1a1a1a;">{{ $alumno->matricula }}</td>
        </tr>
    </table>

    <p style="margin:22px 0 0; font-size:14px; color:#555555; line-height:1.6;">
        Gracias por cerrar tu sesión: así el equipo queda libre para el siguiente
        compañero.
    </p>

@endsection
