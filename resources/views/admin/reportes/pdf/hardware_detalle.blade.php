<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro Físico de Hardware</title>
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

        /* Etiquetas de estado institucionales */
        .badge {
            padding: 3px 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            border: 1px solid #000;
        }

        .badge-disponible {
            background-color: #ffffff;
            color: #000;
        }

        .badge-mantenimiento {
            background-color: #333333;
            color: #ffffff;
        }

        .badge-otro {
            background-color: #f2f2f2;
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
            <td class="header-subtitle">REGISTRO FÍSICO DETALLADO DE HARDWARE</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'N/A') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Total de Equipos (PCs):</b> {{ $totalEquipos }}
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>


    {{-- SECCIÓN 1: INVENTARIO GLOBAL --}}
    <div class="section-title">1. Inventario Institucional (Global)</div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">Equipo / Ubicación</th>
                <th style="width: 15%;">Usos Recientes</th>
                <th style="width: 15%;">Histórico Total</th>
                <th style="width: 15%;">Último Mtto</th>
                <th style="width: 15%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($equiposGlobal as $index => $equipo)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>PC #{{ $equipo->numero_maquina }}</strong><br>
                        <span
                            style="font-size: 9px; color: #555;">{{ strtoupper($equipo->centroComputo->nombre_centro ?? 'N/A') }}</span>
                    </td>
                    <td class="text-center">{{ $equipo->usos_acumulados }}</td>
                    <td class="text-center">{{ $equipo->usos_historicos }}</td>
                    <td class="text-center">
                        {{ $equipo->ultimo_mantenimiento ? \Carbon\Carbon::parse($equipo->ultimo_mantenimiento)->format('d/m/Y') : 'SIN REGISTRO' }}
                    </td>
                    <td class="text-center">
                        @if (in_array(mb_strtolower($equipo->estado ?? ''), ['mantenimiento', 'en mantenimiento']))
                            <span class="badge badge-mantenimiento">MANTENIMIENTO</span>
                        @elseif (in_array(mb_strtolower($equipo->estado ?? ''), ['disponible', 'activo', 'optimo', 'óptimo']))
                            <span class="badge badge-disponible">DISPONIBLE</span>
                        @else
                            <span class="badge badge-otro">{{ strtoupper($equipo->estado) }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="font-style: italic; color: #555;">Sin equipos
                        registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>


    {{-- SECCIÓN 2: DESGLOSE POR LABORATORIO --}}
    <div class="section-title">2. Desglose Detallado por Laboratorio</div>

    @forelse ($equiposLaboratorios as $nombreLab => $equiposLab)
        <div class="sub-section-title">{{ strtoupper($nombreLab) }} ({{ $equiposLab->count() }} PCs)</div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Equipo</th>
                    <th style="width: 20%;">Usos Recientes</th>
                    <th style="width: 20%;">Histórico Total</th>
                    <th style="width: 20%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($equiposLab as $equipo)
                    <tr>
                        <td><strong>PC #{{ $equipo->numero_maquina }}</strong></td>
                        <td class="text-center">{{ $equipo->usos_acumulados }}</td>
                        <td class="text-center">{{ $equipo->usos_historicos }}</td>
                        <td class="text-center">{{ strtoupper($equipo->estado) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center" style="font-style: italic; color: #555;">Sin equipos en
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
