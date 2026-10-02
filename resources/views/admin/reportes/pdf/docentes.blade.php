<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Docentes y Asistencia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Letra pequeña institucional */
            margin: 0;
            padding: 0;
            color: #000;
        }

        /* Tabla de Encabezado Institucional */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            text-align: center;
        }

        .header-table td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
        }

        .header-title {
            font-size: 14px;
            font-weight: bold;
        }

        .header-subtitle {
            font-size: 12px;
            font-weight: bold;
        }

        /* Tabla de Datos (Reporte) */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 6px;
            word-wrap: break-word;
        }

        .report-table th {
            background-color: #1b4478;
            /* Azul oscuro institucional */
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        /* Estilo de las etiquetas (badges) de las materias */
        .badge {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 9px;
            margin-right: 3px;
            margin-bottom: 3px;
            display: inline-block;
            color: #333;
        }
    </style>
</head>

<body>

    {{-- ENCABEZADO INSTITUCIONAL --}}
    <table class="header-table">
        <tr>
            <td rowspan="3" style="width: 15%; text-align: center;">
                @php
                    // Lógica para incrustar el logo en Base64
                    $rutaLogo = public_path(config('marca.logo_documentos'));

                    if (file_exists($rutaLogo)) {
                        $tipo = pathinfo($rutaLogo, PATHINFO_EXTENSION);
                        $data = file_get_contents($rutaLogo);
                        $base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
                    } else {
                        $base64 = '';
                    }
                @endphp

                @if ($base64)
                    <img src="{{ $base64 }}" style="width: 80px; height: auto;" alt="Logo">
                @else
                    <b>{{ config('marca.siglas') }}</b><br><small>Sin Logo</small>
                @endif
            </td>
            <td style="width: 55%;" class="header-title">NOMBRE DEL DOCUMENTO</td>
            <td style="width: 15%; font-weight: bold;">Fecha de generación:</td>
            <td style="width: 15%;">{{ \Carbon\Carbon::now()->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="header-subtitle">REPORTE DE DOCENTES Y ASISTENCIA</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'TODOS') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Sistema:</b> {{ config('marca.sistema') }}
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>


    {{-- TABLA PRINCIPAL DEL REPORTE --}}
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 25%;">Docente</th>
                <th style="width: 30%;">Materias Asignadas</th>
                <th style="width: 15%;">Clases<br><span style="font-size: 8px; font-weight: normal;">(Hoy /
                        Semestre)</span></th>
                <th style="width: 20%;">Asistencia<br><span style="font-size: 8px; font-weight: normal;">(Pres / Falta /
                        Just)</span></th>
                <th style="width: 10%;">% Cump.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteDocentes as $docente)
                <tr>
                    <td>
                        <strong>{{ strtoupper($docente['profesor']) }}</strong><br>
                        <span style="color: #555; font-size: 9px;">{{ $docente['username'] }}</span>
                    </td>
                    <td>
                        @foreach ($docente['materias'] as $materia)
                            <span class="badge">{{ strtoupper($materia) }}</span>
                        @endforeach
                    </td>

                    <td class="text-center" style="background-color: #f9f9f9;">
                        <strong style="color: #1b4478; font-size: 11px;">{{ $docente['esperadas_hoy'] }}</strong>
                        <span style="color: #555;">/ {{ $docente['esperadas_total'] }}</span>
                    </td>

                    <td class="text-center">
                        @if ($docente['esperadas_hoy'] > 0)
                            <span style="color: #000; font-weight: bold;">P: {{ $docente['presentes'] }}</span> |
                            <span style="color: #555;">F: {{ $docente['faltas'] }}</span> |
                            <span style="color: #555;">J: {{ $docente['justificadas'] }}</span>
                        @else
                            <span style="color: #888; font-style: italic;">Sin registros</span>
                        @endif
                    </td>

                    <td class="text-center">
                        @if ($docente['esperadas_hoy'] > 0)
                            <strong
                                style="font-size: 12px; color: {{ $docente['porcentaje'] >= 85 ? '#000' : ($docente['porcentaje'] >= 70 ? '#555' : '#888') }};">
                                {{ $docente['porcentaje'] }}%
                            </strong>
                        @else
                            <span style="color: #888;">N/A</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="font-style: italic; color: #555;">No hay datos de
                        docentes para el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
