@php
    // El color y el texto cambian según cómo quedó registrada la asistencia.
    $estado = $asistencia->estado ?? 'presente';

    $paleta = [
        'presente'    => ['color' => '#009B4D', 'etiqueta' => 'Presente'],
        'falta'       => ['color' => '#dc3545', 'etiqueta' => 'Falta'],
        'justificado' => ['color' => '#FFE900', 'etiqueta' => 'Falta justificada'],
    ];

    $config   = $paleta[$estado] ?? $paleta['presente'];
    $color    = $config['color'];
    $etiqueta = $config['etiqueta'];

    // El amarillo institucional necesita texto oscuro para poder leerse.
    $textoEtiqueta = $estado === 'justificado' ? '#1a1a1a' : '#ffffff';

    $horario  = $asistencia->horario;
    $materia  = optional(optional($horario)->materia)->nombre_materia ?? 'Clase';
    $grupo    = optional(optional($horario)->grupo)->nombre_grupo;
    $profesor = optional(optional($horario)->user);
    $lab      = optional($asistencia->centroComputo)->nombre_centro;

    $fecha = \Carbon\Carbon::parse($asistencia->fecha)->locale('es')->translatedFormat('l d \d\e F \d\e Y');
    $fecha = mb_strtoupper(mb_substr($fecha, 0, 1)) . mb_substr($fecha, 1);
@endphp

@extends('emails.partials.base', ['titulo' => 'Asistencia registrada', 'color' => $color])

@section('contenido')

    <p style="margin:0 0 6px; font-size:15px;">
        Hola <strong>{{ $alumno->nombre_completo }}</strong>,
    </p>

    <p style="margin:0 0 22px; font-size:15px; color:#555555; line-height:1.6;">
        @if ($motivo === 'alumno')
            Tu asistencia quedó registrada correctamente. Estos son los datos:
        @elseif ($motivo === 'profesor')
            Tu profesor pasó lista y así quedó registrada tu asistencia:
        @else
            Se actualizó tu registro de asistencia en esta clase:
        @endif
    </p>

    {{-- Estado, que es lo primero que el alumno quiere ver --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="margin-bottom:22px;">
        <tr>
            <td align="center"
                style="background-color:{{ $color }}; border-radius:8px; padding:18px 12px;">
                <div
                    style="color:{{ $textoEtiqueta }}; font-size:13px; text-transform:uppercase; letter-spacing:1px; opacity:0.85;">
                    Estado
                </div>
                <div style="color:{{ $textoEtiqueta }}; font-size:26px; font-weight:bold; margin-top:4px;">
                    {{ $etiqueta }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Detalle --}}
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
                <td style="padding:6px 18px; color:#777777;">Laboratorio</td>
                <td style="padding:6px 18px; color:#1a1a1a;">{{ $lab }}</td>
            </tr>
        @endif
        @if ($asistencia->equipo_personal)
            <tr>
                <td style="padding:6px 18px; color:#777777;">Equipo</td>
                <td style="padding:6px 18px; color:#1a1a1a;">Equipo personal</td>
            </tr>
        @elseif ($asistencia->numero_maquina)
            <tr>
                <td style="padding:6px 18px; color:#777777;">Equipo</td>
                <td style="padding:6px 18px; color:#1a1a1a;">PC #{{ $asistencia->numero_maquina }}</td>
            </tr>
        @endif
        @if ($profesor && $profesor->name)
            <tr>
                <td style="padding:6px 18px; color:#777777;">Profesor</td>
                <td style="padding:6px 18px; color:#1a1a1a;">
                    {{ $profesor->nombre_completo }}
                </td>
            </tr>
        @endif
        @if ($asistencia->fecha_hora_registro)
            <tr>
                <td style="padding:6px 18px 16px; color:#777777;">Registrado</td>
                <td style="padding:6px 18px 16px; color:#1a1a1a;">
                    {{ \Carbon\Carbon::parse($asistencia->fecha_hora_registro)->format('d/m/Y H:i') }}
                </td>
            </tr>
        @endif
    </table>

    @if (!empty($asistencia->comentario))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
            style="margin-top:18px; background-color:#fffbe6; border-left:4px solid #FFE900; border-radius:4px;">
            <tr>
                <td style="padding:14px 18px; font-size:14px; color:#555555;">
                    <strong style="color:#1a1a1a;">Observación del profesor:</strong><br>
                    {{ $asistencia->comentario }}
                </td>
            </tr>
        </table>
    @endif

    @if ($estado === 'falta')
        <p style="margin:22px 0 0; font-size:14px; color:#555555; line-height:1.6;">
            Si crees que hay un error, habla con tu profesor o acude al Centro de Cómputo
            para revisarlo.
        </p>
    @endif

@endsection
