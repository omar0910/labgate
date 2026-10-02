@php
    $estado = $registro->estado ?? 'asistio';

    $paleta = [
        'asistio'     => ['color' => '#009B4D', 'etiqueta' => 'Clase impartida'],
        'retardo'     => ['color' => '#FFE900', 'etiqueta' => 'Retardo'],
        'justificado' => ['color' => '#FFE900', 'etiqueta' => 'Ausencia justificada'],
        'falta'       => ['color' => '#dc3545', 'etiqueta' => 'Falta'],
    ];

    $config   = $paleta[$estado] ?? $paleta['asistio'];
    $color    = $config['color'];
    $etiqueta = $config['etiqueta'];

    // Sobre el amarillo institucional el texto tiene que ser oscuro.
    $textoEtiqueta = in_array($estado, ['retardo', 'justificado']) ? '#1a1a1a' : '#ffffff';

    $horario = $registro->horario;
    $materia = optional(optional($horario)->materia)->nombre_materia ?? 'Clase';
    $grupo   = optional(optional($horario)->grupo)->nombre_grupo;
    $lab     = optional(optional($horario)->centroComputo)->nombre_centro;

    $fecha = \Carbon\Carbon::parse($registro->fecha)->locale('es')->translatedFormat('l d \d\e F \d\e Y');
    $fecha = mb_strtoupper(mb_substr($fecha, 0, 1)) . mb_substr($fecha, 1);
@endphp

@extends('emails.partials.base', ['titulo' => 'Registro de clase', 'color' => $color])

@section('contenido')

    <p style="margin:0 0 6px; font-size:15px;">
        Hola <strong>{{ $profesor->nombre_completo }}</strong>,
    </p>

    <p style="margin:0 0 22px; font-size:15px; color:#555555; line-height:1.6;">
        El personal del Centro de Cómputo registró el estado de una de tus clases:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-bottom:22px;">
        <tr>
            <td align="center"
                style="background-color:{{ $color }}; border-radius:8px; padding:18px 12px;">
                <div
                    style="color:{{ $textoEtiqueta }}; font-size:13px; text-transform:uppercase; letter-spacing:1px; opacity:0.85;">
                    Estado registrado
                </div>
                <div style="color:{{ $textoEtiqueta }}; font-size:26px; font-weight:bold; margin-top:4px;">
                    {{ $etiqueta }}
                </div>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="background-color:#f4f6f9; border-radius:8px; font-size:14px;">
        <tr>
            <td style="padding:16px 18px 6px; color:#777777; width:38%;">Materia</td>
            <td style="padding:16px 18px 6px; color:#1a1a1a; font-weight:bold;">{{ $materia }}</td>
        </tr>
        @if ($grupo)
            <tr>
                <td style="padding:6px 18px; color:#777777;">Grupo</td>
                <td style="padding:6px 18px; color:#1a1a1a;">{{ $grupo }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:6px 18px; color:#777777;">Fecha</td>
            <td style="padding:6px 18px; color:#1a1a1a;">{{ $fecha }}</td>
        </tr>
        @if ($horario)
            <tr>
                <td style="padding:6px 18px; color:#777777;">Horario</td>
                <td style="padding:6px 18px; color:#1a1a1a;">
                    {{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }} a
                    {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
                </td>
            </tr>
        @endif
        @if ($lab)
            <tr>
                <td style="padding:6px 18px 16px; color:#777777;">Laboratorio</td>
                <td style="padding:6px 18px 16px; color:#1a1a1a;">{{ $lab }}</td>
            </tr>
        @endif
    </table>

    @if (!empty($registro->observaciones))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
            style="margin-top:18px; background-color:#fffbe6; border-left:4px solid #FFE900; border-radius:4px;">
            <tr>
                <td style="padding:14px 18px; font-size:14px; color:#555555;">
                    <strong style="color:#1a1a1a;">Observaciones:</strong><br>
                    {{ $registro->observaciones }}
                </td>
            </tr>
        </table>
    @endif

    @if (in_array($estado, ['falta', 'retardo']))
        <p style="margin:22px 0 0; font-size:14px; color:#555555; line-height:1.6;">
            Si consideras que hay un error o quieres justificarlo, acude al Centro de
            Cómputo para revisarlo.
        </p>
    @endif

@endsection
