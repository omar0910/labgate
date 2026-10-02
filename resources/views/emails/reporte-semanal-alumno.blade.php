<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Resumen Semanal de Asistencias</title>
</head>

<body style="font-family: 'Open Sans', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;">

    <div
        style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

        <div style="background-color: #1a1a1a; padding: 20px; text-align: center; border-bottom: 4px solid #009B4D;">
            <h2 style="color: #FFE900; margin: 0; font-size: 24px;">{{ config('marca.institucion') }}
            </h2>
            <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 16px;">Sistema de Información de Servicios
                Informáticos</p>
        </div>

        <div style="padding: 30px;">
            <h3 style="color: #333333; margin-top: 0;">¡Hola, {{ $alumno->name }}!</h3>
            <p style="color: #555555; line-height: 1.6;">
                Este es tu reporte de actividad y asistencias a los laboratorios durante esta semana. Mantener un buen
                registro es fundamental para tu evaluación.
            </p>

            <div
                style="background-color: #f8f9fa; border-left: 4px solid #009B4D; padding: 15px; margin: 25px 0; border-radius: 4px;">
                <h4 style="color: #009B4D; margin-top: 0; margin-bottom: 15px;">Estadísticas de la Semana</h4>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #555; border-bottom: 1px solid #eeeeee;"><strong>Total de
                                Clases Programadas:</strong></td>
                        <td
                            style="padding: 8px 0; text-align: right; color: #333; font-weight: bold; border-bottom: 1px solid #eeeeee;">
                            {{ $resumen['total'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #555; border-bottom: 1px solid #eeeeee;"><strong>Asistencias
                                Registradas:</strong></td>
                        <td
                            style="padding: 8px 0; text-align: right; color: #009B4D; font-weight: bold; border-bottom: 1px solid #eeeeee;">
                            {{ $resumen['asistencias'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #555; border-bottom: 1px solid #eeeeee;"><strong>Faltas
                                Justificadas:</strong></td>
                        <td
                            style="padding: 8px 0; text-align: right; color: #d39e00; font-weight: bold; border-bottom: 1px solid #eeeeee;">
                            {{ $resumen['justificadas'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #555;"><strong>Faltas:</strong></td>
                        <td style="padding: 8px 0; text-align: right; color: #dc3545; font-weight: bold;">
                            {{ $resumen['faltas'] }}</td>
                    </tr>
                </table>

                {{-- Igual que en su pantalla "Mi Progreso": de dónde salen las faltas que nadie marcó --}}
                @if (($resumen['sin_registro'] ?? 0) > 0)
                    <p style="color: #555555; font-size: 13px; margin: 12px 0 0;">
                        Incluye {{ $resumen['sin_registro'] }}
                        {{ $resumen['sin_registro'] == 1 ? 'clase' : 'clases' }} en las que no te registraste y tus
                        compañeros sí.
                    </p>
                @endif
            </div>

            <h4
                style="color: #333333; margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #eeeeee; padding-bottom: 5px;">
                Detalle por Materia</h4>

            @foreach ($desgloseMaterias as $materia => $datos)
                <div
                    style="background-color: #ffffff; border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 12px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">

                    <div style="margin-bottom: 12px; border-bottom: 1px solid #eeeeee; padding-bottom: 8px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="text-align: left;">
                                    <strong style="color: #009B4D; font-size: 16px;">{{ $materia }}</strong>
                                </td>
                                <td style="text-align: right;">
                                    <span
                                        style="background-color: {{ $datos['porcentaje'] >= 80 ? '#e8f5e9' : '#fbe3e4' }}; color: {{ $datos['porcentaje'] >= 80 ? '#009B4D' : '#dc3545' }}; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 14px;">
                                        {{ $datos['porcentaje'] }}%
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <table style="width: 100%; font-size: 13px; text-align: center;">
                        <tr>
                            <td style="color: #555; width: 25%;">Total<br><strong>{{ $datos['total'] }}</strong></td>
                            <td style="color: #009B4D; width: 25%;">
                                Asistencias<br><strong>{{ $datos['presentes'] }}</strong></td>
                            <td style="color: #d39e00; width: 25%;">
                                Justificadas<br><strong>{{ $datos['justificadas'] }}</strong></td>
                            <td style="color: #dc3545; width: 25%;">Faltas<br><strong>{{ $datos['faltas'] }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach

            <div
                style="text-align: center; margin-top: 35px; background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
                <p style="color: #555555; margin-bottom: 10px; font-size: 15px;">
                    Tu <strong style="color: #333;">Porcentaje General</strong> (englobando todas tus materias) esta
                    semana es:
                </p>
                <div
                    style="font-size: 36px; font-weight: 900; color: {{ $resumen['porcentaje'] >= 80 ? '#009B4D' : '#dc3545' }};">
                    {{ $resumen['porcentaje'] }}%
                </div>

                @if ($resumen['porcentaje'] < 80)
                    <p
                        style="color: #dc3545; font-size: 14px; margin-top: 15px; background-color: #fff; padding: 10px; border-radius: 4px; border-left: 3px solid #dc3545;">
                        ⚠️ <strong>Atención:</strong> Te recomendamos mejorar tu asistencia general la próxima semana
                        para evitar problemas con tus evaluaciones en los laboratorios.
                    </p>
                @endif

                {{-- NUEVA NOTA: Aviso para aclarar faltas con el docente --}}
                @if ($resumen['faltas'] > 0)
                    <p
                        style="color: #555555; font-size: 13px; margin-top: 15px; background-color: #fff; padding: 10px; border-radius: 4px; border-left: 3px solid #009B4D; text-align: left;">
                        💡 <strong>Nota:</strong> Si consideras que existe un error en tu registro de asistencia o
                        presentas faltas que desconoces, te sugerimos acercarte directamente con tu
                        <strong>docente</strong> de la materia para realizar la aclaración necesaria.
                    </p>
                @endif
            </div>

        </div>

        <div style="background-color: #f4f6f9; padding: 15px; text-align: center; font-size: 12px; color: #777777;">
            <p style="margin: 0;">Este es un mensaje automático generado por el Sistema de Información de Servicios
                Informáticos.</p>
            <p style="margin: 5px 0 0 0;">Por favor no responda a este correo.</p>
        </div>
    </div>

</body>

</html>
