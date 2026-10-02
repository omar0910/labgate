<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Bitácora de Uso Libre</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            color: #000;
        }

        /* Configuración de la página */
        @page {
            margin: 30px 40px 50px 40px;
            /* Margen: Arriba, Derecha, Abajo, Izquierda */
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

        /* Caja de Información (Filtros) */
        .summary-box {
            background-color: #f2f2f2;
            padding: 8px 10px;
            border: 1px solid #000;
            margin-bottom: 15px;
            font-size: 10px;
            line-height: 1.4;
        }

        .summary-box table {
            width: 100%;
            border: none;
        }

        .summary-box td {
            border: none;
            padding: 2px;
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
            font-size: 10px;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: bold;
        }

        .text-danger {
            color: #333;
            /* Gris oscuro para el estado 'En curso' */
            font-style: italic;
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
            <td class="header-subtitle">BITÁCORA OFICIAL DE USO LIBRE</td>
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
            <td>Bitácora de Accesos</td>
        </tr>
    </table>

    {{-- CAJA DE INFORMACIÓN DE FILTROS --}}
    <div class="summary-box">
        <table>
            <tr>
                <td><strong>FILTRO APLICADO:</strong>
                    @if ($fechaInicio && $fechaFin)
                        DEL {{ strtoupper(\Carbon\Carbon::parse($fechaInicio)->format('d/m/Y')) }} AL
                        {{ strtoupper(\Carbon\Carbon::parse($fechaFin)->format('d/m/Y')) }}
                    @elseif($fechaInicio)
                        DÍA {{ strtoupper(\Carbon\Carbon::parse($fechaInicio)->format('d/m/Y')) }}
                    @else
                        TODO EL SEMESTRE
                    @endif
                </td>
                <td style="text-align: right;"><strong>TOTAL REGISTROS:</strong> {{ $registros->count() }}</td>
            </tr>
        </table>
    </div>

    {{-- TABLA PRINCIPAL DEL REPORTE --}}
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 12%;">Folio / Fecha</th>
                <th style="width: 33%;">Estudiante</th>
                <th style="width: 25%;">Laboratorio / PC</th>
                <th style="width: 10%;" class="text-center">Entrada</th>
                <th style="width: 10%;" class="text-center">Salida</th>
                <th style="width: 10%;" class="text-center">Duración</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $reg)
                @php
                    $entrada = \Carbon\Carbon::parse($reg->fecha_hora_registro);
                    $salida = $reg->fecha_hora_salida ? \Carbon\Carbon::parse($reg->fecha_hora_salida) : null;
                    $minutosTotales = $salida ? $entrada->diffInMinutes($salida) : 0;
                    $horas = floor($minutosTotales / 60);
                    $minutos = $minutosTotales % 60;
                @endphp
                <tr>
                    <td>
                        <span class="fw-bold">#{{ str_pad($reg->id, 5, '0', STR_PAD_LEFT) }}</span><br>
                        <span
                            style="font-size: 9px; color: #555;">{{ \Carbon\Carbon::parse($reg->fecha)->format('d/m/Y') }}</span>
                    </td>
                    <td>
                        <div class="fw-bold">{{ strtoupper($reg->user->name . ' ' . $reg->user->apellido_paterno) }}
                        </div>
                        <span style="color: #555; font-size: 9px;">MAT:
                            {{ strtoupper($reg->user->matricula ?? 'S/M') }}</span>
                    </td>
                    <td>
                        {{ strtoupper($reg->centroComputo->nombre_centro) }}<br>
                        <span style="color: #1b4478; font-weight: bold; font-size: 9px;">EQUIPO
                            #{{ $reg->numero_maquina }}</span>
                    </td>
                    <td class="text-center">{{ strtoupper($entrada->format('h:i A')) }}</td>
                    <td class="text-center">{{ $salida ? strtoupper($salida->format('h:i A')) : '---' }}</td>
                    <td class="text-center fw-bold">
                        @if ($salida)
                            {{ $horas > 0 ? $horas . 'H ' : '' }}{{ $minutos }}M
                        @else
                            <span class="text-danger">EN CURSO</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="font-style: italic; color: #555; padding: 20px;">
                        No hay registros de uso libre en las fechas seleccionadas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
