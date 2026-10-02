<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Uso de Infraestructura</title>
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

        .page-break {
            page-break-before: always;
        }

        .avoid-break {
            page-break-inside: avoid;
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
            padding: 6px;
            border: 1px solid #000;
            text-transform: uppercase;
        }

        /* Caja de Resumen */
        .summary-box {
            background-color: #f2f2f2;
            padding: 8px 10px;
            border: 1px solid #000;
            margin-bottom: 15px;
            font-size: 10px;
            line-height: 1.4;
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

    {{-- ENCABEZADO INSTITUCIONAL PÁGINA 1 --}}
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
            <td class="header-subtitle">REPORTE INTEGRAL DE INFRAESTRUCTURA Y DESGASTE</td>
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

    <div class="summary-box">
        <strong>RESUMEN ANALÍTICO:</strong> Este documento detalla la carga de trabajo de los centros de cómputo, así
        como el registro histórico en horas y el desgaste físico actual de los equipos durante el periodo seleccionado.
    </div>

    {{-- SECCIÓN 1 --}}
    <div class="avoid-break">
        <div class="section-title">1. Impacto General por Laboratorio</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Laboratorio</th>
                    <th style="width: 16%;">Sesiones de Clase</th>
                    <th style="width: 16%;">Con Equipo Personal</th>
                    <th style="width: 16%;">Accesos Uso Libre</th>
                    <th style="width: 16%;">Impacto Total</th>
                    <th style="width: 16%;">Hora Pico Detectada</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporteLaboratorios as $lab)
                    <tr>
                        <td><strong>{{ strtoupper($lab['nombre']) }}</strong></td>
                        <td class="text-center">{{ $lab['sesiones'] }}</td>
                        <td class="text-center">{{ $lab['equipo_personal'] ?? 0 }}</td>
                        <td class="text-center">{{ $lab['uso_libre'] }}</td>
                        <td class="text-center"><strong>{{ $lab['total_impacto'] }}</strong></td>
                        <td class="text-center">{{ $lab['hora_pico'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- SECCIÓN 2 --}}
    <div class="avoid-break">
        <div class="section-title">2. Récord Histórico Semestral (Top 25 Equipos)</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Ubicación y Equipo</th>
                    <th style="width: 25%;">Total Sesiones Asignadas</th>
                    <th style="width: 25%;">Horas Reales de Uso</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topMaquinasHistorico->take(25) as $hist)
                    @php
                        $horas = floor($hist->total_minutos / 60);
                        $minutos = $hist->total_minutos % 60;
                    @endphp
                    <tr>
                        <td><strong>PC #{{ $hist->numero_maquina }}</strong> -
                            {{ strtoupper($hist->centroComputo->nombre_centro ?? 'N/A') }}</td>
                        <td class="text-center">{{ $hist->total_usos }}</td>
                        <td class="text-center">{{ $horas }}h {{ $minutos }}m</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- SECCIÓN 3 --}}
    <div class="avoid-break">
        <div class="section-title">3. Registro de Hardware y Mantenimiento</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Equipo / Ubicación</th>
                    <th style="width: 15%;">Usos Recientes</th>
                    <th style="width: 15%;">Histórico Total</th>
                    <th style="width: 20%;">Último Mantenimiento</th>
                    <th style="width: 20%;">Estado Actual</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($equiposDesgaste as $equipo)
                    <tr>
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
                        <td class="text-center">{{ strtoupper($equipo->estado) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- NUEVA PÁGINA: ANÁLISIS DE CARRERAS --}}
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
            <td class="header-subtitle">ANÁLISIS ACADÉMICO POR CARRERA</td>
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

    <div class="summary-box">
        <strong>NOTA:</strong> Estadísticas basadas en el número de estudiantes únicos contabilizados durante el periodo
        seleccionado.
    </div>

    {{-- SECCIÓN 4 --}}
    <div class="avoid-break">
        <div class="section-title">4. Impacto Global Institucional</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Programa Educativo</th>
                    <th style="width: 25%;">Total Alumnos (Únicos)</th>
                    <th style="width: 25%;">Porcentaje de Alcance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reporteCarreras as $dato)
                    <tr>
                        <td>{{ strtoupper($dato['carrera']) }}</td>
                        <td class="text-center">{{ $dato['cantidad'] }}</td>
                        <td class="text-center">{{ $dato['porcentaje'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center" style="font-style: italic; color: #555;">No hay datos
                            suficientes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- SECCIÓN 5 --}}
    <div class="avoid-break">
        <div class="section-title">5. Preferencia de Programa por Laboratorio (Top 3)</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 35%;">Laboratorio</th>
                    <th style="width: 65%;">Principales Usuarios (Alumnos Únicos)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporteLaboratorios as $lab)
                    <tr>
                        <td><strong>{{ strtoupper($lab['nombre']) }}</strong></td>
                        <td style="padding: 10px;">
                            @forelse($lab['top_carreras'] as $tc)
                                <div style="margin-bottom: 3px;">
                                    • {{ strtoupper($tc['carrera']) }}: <strong>{{ $tc['cantidad'] }} alumnos</strong>
                                    ({{ $tc['porcentaje'] }}%)
                                </div>
                            @empty
                                <span style="color: #555; font-style: italic;">Sin registros únicos en este
                                    periodo.</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>

</html>
