@php
    $lab     = optional($sesion->centroComputo)->nombre_centro ?? 'Laboratorio';
    $entrada = \Carbon\Carbon::parse($sesion->fecha_hora_registro);
    $fecha   = $entrada->locale('es')->translatedFormat('l d \d\e F \d\e Y');
    $fecha   = mb_strtoupper(mb_substr($fecha, 0, 1)) . mb_substr($fecha, 1);
@endphp

@extends('emails.partials.base', ['titulo' => 'Entrada en Uso Libre', 'color' => '#009B4D'])

@section('contenido')

    <p style="margin:0 0 6px; font-size:15px;">
        Hola <strong>{{ $alumno->nombre_completo }}</strong>,
    </p>

    <p style="margin:0 0 22px; font-size:15px; color:#555555; line-height:1.6;">
        Tu sesión de <strong>uso libre</strong> quedó abierta. Estos son los datos del
        equipo que estás usando:
    </p>

    {{-- El equipo y la hora, que es lo que el alumno necesita recordar --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-bottom:22px;">
        <tr>
            <td width="50%" align="center"
                style="background-color:#009B4D; border-radius:8px 0 0 8px; padding:18px 12px;">
                <div
                    style="color:#ffffff; font-size:12px; text-transform:uppercase; letter-spacing:1px; opacity:0.85;">
                    Equipo
                </div>
                <div style="color:#ffffff; font-size:24px; font-weight:bold; margin-top:4px;">
                    PC #{{ $sesion->numero_maquina }}
                </div>
            </td>
            <td width="50%" align="center"
                style="background-color:#1a1a1a; border-radius:0 8px 8px 0; padding:18px 12px;">
                <div
                    style="color:#FFE900; font-size:12px; text-transform:uppercase; letter-spacing:1px;">
                    Hora de entrada
                </div>
                <div style="color:#ffffff; font-size:24px; font-weight:bold; margin-top:4px;">
                    {{ $entrada->format('H:i') }}
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
            <td style="padding:6px 18px; color:#777777;">Fecha</td>
            <td style="padding:6px 18px; color:#1a1a1a;">{{ $fecha }}</td>
        </tr>
        <tr>
            <td style="padding:6px 18px 16px; color:#777777;">Alumno</td>
            <td style="padding:6px 18px 16px; color:#1a1a1a;">
                {{ $alumno->matricula }}
            </td>
        </tr>
    </table>

    {{-- El recordatorio, que es el motivo principal de este correo --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-top:22px; background-color:#fffbe6; border-left:4px solid #FFE900; border-radius:4px;">
        <tr>
            <td style="padding:16px 18px; font-size:14px; color:#555555; line-height:1.6;">
                <strong style="color:#1a1a1a;">No olvides cerrar tu sesión al terminar.</strong><br>
                Entra a tu panel del sistema y pulsa <em>Terminar sesión de uso libre</em>.
                Si no lo haces, el equipo seguirá apareciendo como ocupado y nadie más
                podrá usarlo.
            </td>
        </tr>
    </table>

@endsection
