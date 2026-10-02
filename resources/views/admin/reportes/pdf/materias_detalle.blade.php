<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Auditoría Académica</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Margen inferior amplio para dar espacio al número de página */
            margin: 0 0 40px 0;
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

        /* Caja de Resumen / Filtros */
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
            /* Un poco más chico para que quepan las columnas */
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: bold;
        }

        /* Colores adaptados para un reporte formal (escala de grises y oscuros) */
        .text-danger {
            color: #333;
            /* En vez de rojo brillante, un gris oscuro intenso para faltas */
            font-weight: bold;
        }

        .text-success {
            color: #000;
            /* Negro fuerte para presencias/impartidas */
            font-weight: bold;
        }

        .bg-light {
            background-color: #f9f9f9;
        }
    </style>
</head>

<body>

    {{-- SCRIPT PARA NUMERACIÓN DE PÁGINA (Garantizado que funciona en DomPDF) --}}
    <script type="text/php">
        if (isset($pdf)) {
            // Se inyecta en cada página generada
            $pdf->page_script('
                $text = "Página " . $PAGE_NUM . " de " . $PAGE_COUNT;
                $font = $fontMetrics->get_font("Arial, Helvetica, sans-serif", "normal");
                $size = 9;
                $color = array(0,0,0);
                
                // Coordenadas: X (ancho - 120px) , Y (alto - 30px)
                $x = $pdf->get_width() - 100;
                $y = $pdf->get_height() - 35;
                
                $pdf->text($x, $y, $text, $font, $size, $color);
            ');
        }
    </script>

    {{-- ENCABEZADO INSTITUCIONAL --}}
    <table class="header-table">
        <tr>
            <td rowspan="3" style="width: 15%; text-align: center;">
                @php
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
            <td class="header-subtitle">AUDITORÍA DE RENDIMIENTO POR ASIGNATURA</td>
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
            <td>Auditoría Académica</td>
        </tr>
    </table>

    {{-- CAJA DE INFORMACIÓN DEL FILTRO --}}
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
                <td style="text-align: right;"><strong>MATERIAS ANALIZADAS:</strong> {{ $reporteMaterias->count() }}
                </td>
            </tr>
        </table>
    </div>

    {{-- TABLA PRINCIPAL DEL REPORTE --}}
    <table class="report-table">
        <thead>
            <tr>
                {{-- Los anchos suman exactamente 100% --}}
                <th style="width: 22%;">Asignatura / Grupo</th>
                <th style="width: 24%;">Docente / Laboratorio</th>
                <th style="width: 10%;">Prog.<br><span
                        style="font-size: 7px; font-weight: normal; text-transform: none;">(Hoy / Sem)</span></th>
                <th style="width: 7%;">Imp.</th>
                <th style="width: 7%;">Faltas</th>
                <th style="width: 7%;">Justif.</th>
                <th style="width: 7%;">Cump.</th>
                <th style="width: 16%;">Alumnos<br><span
                        style="font-size: 7px; font-weight: normal; text-transform: none;">(Prom / Grupo)</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteMaterias as $item)
                <tr>
                    <td>
                        <span class="fw-bold" style="font-size: 11px;">{{ strtoupper($item['materia']) }}</span>

                        {{-- Indicador de Reserva Especial para el PDF --}}
                        @if (isset($item['es_especial']) && $item['es_especial'])
                            <br><span style="font-size: 8px; color: #555; font-weight: bold;">(RESERVA ESPECIAL)</span>
                        @endif

                        <br>
                        <span style="color: #666; font-size: 9px;">GRUPO: {{ strtoupper($item['grupo']) }}</span>
                    </td>
                    <td>
                        <strong>{{ strtoupper($item['docente']) }}</strong><br>
                        <span
                            style="color: #1b4478; font-size: 9px; font-weight: bold;">{{ strtoupper($item['laboratorio']) }}</span>
                    </td>

                    {{-- CÁLCULO MATEMÁTICO REAL: HASTA HOY / TOTAL --}}
                    <td class="text-center bg-light">
                        <span class="fw-bold" style="font-size: 11px; color: #1b4478;">
                            {{ $item['esperadas_hoy'] }}
                        </span>
                        <span style="font-size: 9px; color: #666; font-weight: bold;">/
                            {{ $item['esperadas_total'] }}</span>
                    </td>

                    <td class="text-center fw-bold text-success">{{ $item['impartidas'] }}</td>
                    <td class="text-center fw-bold {{ $item['faltas'] > 0 ? 'text-danger' : '' }}">
                        {{ $item['faltas'] }}</td>
                    <td class="text-center fw-bold">{{ $item['justificadas'] }}</td>

                    <td class="text-center">
                        <strong
                            style="color: {{ $item['color_cumplimiento'] == 'success' ? '#000' : ($item['color_cumplimiento'] == 'warning' ? '#555' : '#888') }}">
                            {{ $item['porcentaje_cumplimiento'] }}%
                        </strong>
                    </td>

                    <td class="text-center bg-light">
                        <span class="fw-bold"
                            style="font-size: 11px; color: {{ $item['asistencia_promedio'] >= 15 ? '#000' : '#555' }};">
                            {{ $item['asistencia_promedio'] }}
                        </span>
                        <span style="font-size: 10px; color: #333; font-weight: bold;">/
                            {{ $item['alumnos_unicos'] }}</span>
                        <br>
                        <span style="font-size: 8px; color: #777;">Promedio asiste</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="font-style: italic; color: #555; padding: 20px;">
                        No hay información académica en las fechas seleccionadas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
