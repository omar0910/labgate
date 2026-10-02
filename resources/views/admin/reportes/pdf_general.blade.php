<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencia</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        .header p {
            margin: 2px 0;
            color: #555;
        }

        /* Información del Reporte */
        .info-box {
            width: 100%;
            margin-bottom: 15px;
        }

        .info-box td {
            padding: 5px;
        }

        .label {
            font-weight: bold;
        }

        /* Tabla de Datos */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.data th,
        table.data td {
            border: 1px solid #999;
            padding: 6px;
            text-align: left;
        }

        table.data th {
            background-color: #eee;
            text-transform: uppercase;
            font-size: 10px;
        }

        /* Colores de estado */
        .tipo-clase {
            color: #0056b3;
            font-weight: bold;
        }

        .tipo-libre {
            color: #198754;
            font-weight: bold;
        }

        /* Pie de página */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>

<body>

    {{-- ENCABEZADO INSTITUCIONAL --}}
    <div class="header">
        <h1>{{ config('marca.institucion') }}</h1>
        <p>Sistema de Gestión de Centros de Cómputo</p>
        <p><strong>Reporte General de Asistencia</strong></p>
    </div>

    {{-- RESUMEN DEL FILTRO --}}
    <table class="info-box">
        <tr>
            <td class="label">Periodo:</td>
            <td>Del {{ $datos['inicio'] }} al {{ $datos['fin'] }}</td>
            <td class="label">Laboratorio:</td>
            <td>{{ $datos['filtro_lab'] }}</td>
        </tr>
        <tr>
            <td class="label">Total Registros:</td>
            <td>{{ $datos['total'] }} asistencias</td>
            <td class="label">Fecha Emisión:</td>
            <td>{{ date('d/m/Y H:i A') }}</td>
        </tr>
    </table>

    {{-- TABLA DE DATOS --}}
    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Alumno</th>
                <th>Matrícula</th>
                <th>Actividad</th>
                <th>PC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asistencias as $registro)
                <tr>
                    {{-- Fecha y Hora --}}
                    <td>{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                    <td>
                        {{-- CAMBIO 1: Usamos fecha_hora_registro --}}
                        {{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('H:i') }}
                        -
                        {{-- CAMBIO 2: Usamos fecha_hora_salida --}}
                        {{ $registro->fecha_hora_salida ? \Carbon\Carbon::parse($registro->fecha_hora_salida)->format('H:i') : 'Activo' }}
                    </td>

                    {{-- Alumno --}}
                    <td>
                        {{ $registro->user->apellido_paterno }} {{ $registro->user->apellido_materno }}
                        {{ $registro->user->name }}
                    </td>
                    <td>{{ $registro->user->matricula }}</td>

                    {{-- Actividad (Clase o Uso Libre) --}}
                    <td>
                        @if ($registro->tipo == 'Clase')
                            <span class="tipo-clase">Clase:</span>
                            {{ $registro->horario->materia->nombre_materia ?? 'N/A' }}
                            <br>
                            <small>(Prof. {{ $registro->horario->user->apellido_paterno ?? '' }})</small>
                        @else
                            <span class="tipo-libre">Uso Libre</span>
                        @endif
                    </td>

                    {{-- Equipo --}}
                    <td style="text-align: center;">
                        {{ $registro->equipo_personal ? 'Equipo personal' : ($registro->numero_maquina ? '#' . $registro->numero_maquina : '—') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- PIE DE PÁGINA --}}
    <div class="footer">
        Este documento fue generado automáticamente por el sistema. | Página <span class="pagenum"></span>
    </div>

</body>

</html>
