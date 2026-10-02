<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Récord Histórico de Equipos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Letra pequeña institucional */
            margin: 0;
            padding: 0;
            color: #000;
        }

        /* Configuración de la página */
        @page {
            margin: 30px 40px 50px 40px;
            /* Margen: Arriba, Derecha, Abajo, Izquierda */
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
            vertical-align: middle;
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

    {{-- ENCABEZADO INSTITUCIONAL PÁGINA 1 --}}
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
            <td class="header-subtitle">RÉCORD HISTÓRICO DE EQUIPOS (CARGA DE TRABAJO)</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'N/A') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Alcance:</b> Todos los Centros de Cómputo
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>

    {{-- SECCIÓN 1: RANKING GLOBAL --}}
    <div class="section-title">1. Ranking Institucional (Global)</div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 10%;"># Rango</th>
                <th style="width: 40%;">Equipo / Ubicación</th>
                <th style="width: 25%;">Total Sesiones</th>
                <th style="width: 25%;">Horas de Uso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recordGlobal as $index => $hist)
                @php
                    $horas = floor($hist->total_minutos / 60);
                    $minutos = $hist->total_minutos % 60;
                @endphp
                <tr>
                    <td class="text-center fw-bold">{{ $index + 1 }}</td>
                    <td>
                        <strong>PC #{{ $hist->numero_maquina }}</strong><br>
                        <span
                            style="font-size: 9px; color: #555;">{{ strtoupper($hist->centroComputo->nombre_centro ?? 'N/A') }}</span>
                    </td>
                    <td class="text-center">{{ $hist->total_usos }} sesiones</td>
                    <td class="text-center"><strong>{{ $horas }}h {{ $minutos }}m</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="font-style: italic; color: #555;">Sin registros de
                        uso.</td>
                </tr>
            @endforelse
        </tbody>
    </table>


    <div class="page-break"></div>


    {{-- ENCABEZADO INSTITUCIONAL PÁGINA 2 --}}
    <table class="header-table">
        <tr>
            <td rowspan="3" style="width: 15%; text-align: center;">
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
            <td class="header-subtitle">RÉCORD HISTÓRICO DE EQUIPOS - DESGLOSE POR LABORATORIO</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'N/A') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Alcance:</b> Laboratorios Individuales
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>


    {{-- SECCIÓN 2: DESGLOSE POR LABORATORIO --}}
    <div class="section-title">2. Desglose Detallado por Laboratorio</div>

    @forelse ($recordLaboratorios as $nombreLab => $equiposLab)
        <div class="sub-section-title">{{ strtoupper($nombreLab) }}</div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Equipo</th>
                    <th style="width: 30%;">Total Sesiones</th>
                    <th style="width: 30%;">Horas de Uso</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($equiposLab as $hist)
                    @php
                        $horas = floor($hist->total_minutos / 60);
                        $minutos = $hist->total_minutos % 60;
                    @endphp
                    <tr>
                        <td><strong>PC #{{ $hist->numero_maquina }}</strong></td>
                        <td class="text-center">{{ $hist->total_usos }} sesiones</td>
                        <td class="text-center">{{ $horas }}h {{ $minutos }}m</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center" style="font-style: italic; color: #555;">Sin registros en
                            este laboratorio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p style="font-style: italic; color: #555; text-align: center; margin-top: 20px;">No hay datos registrados por
            laboratorio.</p>
    @endforelse

</body>

</html>
