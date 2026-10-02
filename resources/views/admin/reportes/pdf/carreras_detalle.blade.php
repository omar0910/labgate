<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte Detallado de Carreras</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Letra pequeña institucional */
            margin: 0;
            padding: 0;
            color: #000;
        }

        /* Utilidad para forzar el salto de página en PDFs */
        .page-break {
            page-break-before: always;
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

        /* Títulos de Sección */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #000;
            margin-top: 15px;
            margin-bottom: 5px;
            background-color: #e6e6e6;
            /* Gris muy claro para resaltar */
            padding: 6px;
            border: 1px solid #000;
            text-transform: uppercase;
        }

        .sub-section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1b4478;
            /* Azul institucional */
            margin-top: 15px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        /* Tabla de Datos (Reporte) */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-bottom: 20px;
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
            <td class="header-subtitle">REPORTE ANALÍTICO DETALLADO DE CARRERAS</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'N/A') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Total de Estudiantes Únicos:</b> {{ $totalAlumnosUnicos }}
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>


    {{-- SECCIÓN 1: IMPACTO GLOBAL --}}
    <div class="section-title">1. Impacto Institucional (Global)</div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Programa Educativo</th>
                <th style="width: 25%;">Estudiantes Únicos</th>
                <th style="width: 25%;">Porcentaje (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporteCarreras as $index => $dato)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ strtoupper($dato['carrera']) }}</td>
                    <td class="text-center">{{ $dato['cantidad'] }}</td>
                    <td class="text-center">{{ $dato['porcentaje'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>

    {{-- SECCIÓN 2: DESGLOSE POR LABORATORIO --}}
    <div class="section-title">2. Desglose Detallado por Laboratorio</div>

    @foreach ($reporteLaboratorios as $lab)
        <div class="sub-section-title">{{ strtoupper($lab['nombre']) }} ({{ $lab['total_alumnos'] }} ALUMNOS)</div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Programa Educativo</th>
                    <th style="width: 25%;">Alumnos</th>
                    <th style="width: 25%;">Porcentaje (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lab['carreras'] as $carrera)
                    <tr>
                        <td>{{ strtoupper($carrera['carrera']) }}</td>
                        <td class="text-center">{{ $carrera['cantidad'] }}</td>
                        <td class="text-center">{{ $carrera['porcentaje'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center" style="font-style: italic; color: #555;">Sin registros en
                            este laboratorio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

</body>

</html>
