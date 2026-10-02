<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resumen General del Centro de Cómputo</title>
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

        /* Título de cada bloque del reporte */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #e9e9e9;
            border: 1px solid #000;
            padding: 5px 6px;
            margin-top: 14px;
            margin-bottom: 0;
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

        .cifra {
            font-size: 13px;
            font-weight: bold;
        }

        .nota {
            font-size: 9px;
            color: #555;
            margin-top: 12px;
        }

        .vacio {
            font-style: italic;
            color: #555;
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
            <td class="header-subtitle">RESUMEN GENERAL DEL CENTRO DE CÓMPUTO</td>
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


    {{-- 1. CIFRAS CLAVE --}}
    <p class="section-title">1. Cifras clave del periodo</p>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 28%;">Indicador</th>
                <th style="width: 17%;">Valor</th>
                <th style="width: 55%;">Desglose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Accesos registrados</strong></td>
                <td class="text-center cifra">{{ number_format($totalAccesos) }}</td>
                <td>
                    Clases: <strong>{{ number_format($accesosClase) }}</strong> &nbsp;|&nbsp;
                    Uso libre: <strong>{{ number_format($accesosUsoLibre) }}</strong>
                </td>
            </tr>
            <tr>
                <td><strong>Clases agendadas</strong></td>
                <td class="text-center cifra">{{ number_format($totalClases) }}</td>
                <td>
                    Recurrentes: <strong>{{ $clasesFijas }}</strong>
                    ({{ $sesionesPorSemana ?? 0 }} sesiones por semana) &nbsp;|&nbsp;
                    Especiales: <strong>{{ $clasesEspeciales }}</strong>
                </td>
            </tr>
            <tr>
                <td><strong>Docentes activos</strong></td>
                <td class="text-center cifra">{{ number_format($docentesActivos) }}</td>
                <td>
                    Con clase fija: <strong>{{ $docentesFijos }}</strong> &nbsp;|&nbsp;
                    Con reserva: <strong>{{ $docentesEspeciales }}</strong>
                </td>
            </tr>
            <tr>
                <td><strong>Cumplimiento de clases</strong></td>
                <td class="text-center cifra">
                    {{ $cumplimiento !== null ? $cumplimiento . '%' : 'N/D' }}
                </td>
                <td>
                    @if ($cumplimiento !== null)
                        {{ number_format($clasesCumplidas) }} de {{ number_format($esperadasHoy) }} clases impartidas
                        a la fecha de corte &nbsp;|&nbsp; Faltas acumuladas: <strong>{{ $faltasTotales }}</strong>
                    @else
                        <span class="vacio">Aún no hay clases que debieran haberse impartido en este periodo.</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>


    {{-- 2. USO POR LABORATORIO --}}
    <p class="section-title">2. Uso por laboratorio</p>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 30%;">Laboratorio</th>
                <th style="width: 17%;">Sesiones de clase</th>
                <th style="width: 17%;">Accesos uso libre</th>
                <th style="width: 16%;">Total</th>
                <th style="width: 20%;">Hora pico</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteLaboratorios as $lab)
                <tr>
                    <td><strong>{{ strtoupper($lab['nombre']) }}</strong></td>
                    <td class="text-center">{{ number_format($lab['sesiones']) }}</td>
                    <td class="text-center">{{ number_format($lab['uso_libre']) }}</td>
                    <td class="text-center"><strong>{{ number_format($lab['total_impacto']) }}</strong></td>
                    <td class="text-center">{{ $lab['hora_pico'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center vacio">Sin actividad registrada en el periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>


    {{-- 3. CARRERAS ATENDIDAS --}}
    <p class="section-title">3. Carreras atendidas (alumnos distintos)</p>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 60%;">Programa educativo</th>
                <th style="width: 20%;">Alumnos únicos</th>
                <th style="width: 20%;">% del total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteCarreras as $c)
                <tr>
                    <td>{{ strtoupper($c['carrera']) }}</td>
                    <td class="text-center">{{ number_format($c['cantidad']) }}</td>
                    <td class="text-center">{{ $c['porcentaje'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center vacio">No hay alumnos registrados en el periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>


    {{-- 4. CUMPLIMIENTO POR DOCENTE --}}
    <p class="section-title">4. Docentes por debajo del 85% de cumplimiento</p>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 40%;">Docente</th>
                <th style="width: 15%;">Impartidas</th>
                <th style="width: 15%;">Faltas</th>
                <th style="width: 15%;">Justificadas</th>
                <th style="width: 15%;">% Cump.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($docentesAtencion as $d)
                <tr>
                    <td><strong>{{ strtoupper($d['profesor']) }}</strong></td>
                    <td class="text-center">{{ $d['presentes'] }} / {{ $d['esperadas_hoy'] }}</td>
                    <td class="text-center">{{ $d['faltas'] }}</td>
                    <td class="text-center">{{ $d['justificadas'] }}</td>
                    <td class="text-center"><strong>{{ $d['porcentaje'] }}%</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center vacio">
                        Ningún docente está por debajo del 85% de cumplimiento en este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>


    {{-- 5. EQUIPOS CON MÁS USO --}}
    <p class="section-title">5. Equipos con más uso (candidatos a mantenimiento preventivo)</p>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 20%;">Equipo</th>
                <th style="width: 30%;">Laboratorio</th>
                <th style="width: 17%;">Usos recientes</th>
                <th style="width: 18%;">Último mtto.</th>
                <th style="width: 15%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($equiposDesgaste as $eq)
                <tr>
                    <td><strong>PC #{{ $eq->numero_maquina }}</strong></td>
                    <td>{{ $eq->centroComputo->nombre_centro ?? 'Sin laboratorio' }}</td>
                    <td class="text-center">{{ number_format($eq->usos_acumulados) }}</td>
                    <td class="text-center">
                        {{ $eq->ultimo_mantenimiento ? \Carbon\Carbon::parse($eq->ultimo_mantenimiento)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td class="text-center">{{ ucfirst($eq->estado) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center vacio">No hay equipos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="nota">
        Corte al {{ \Carbon\Carbon::now()->format('d/m/Y') }}.
        El cumplimiento se calcula sobre las clases que ya debieron impartirse a esta fecha,
        descontando los días inhábiles registrados para el periodo.
    </p>

</body>

</html>
