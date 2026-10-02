<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Actividad de Alumnos</title>
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

        /* Estilo de las etiquetas (badges) de los laboratorios */
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
                    // Lógica para incrustar el logo en Base64 (Igual que en horarios)
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
            <td class="header-subtitle">REPORTE DE ACTIVIDAD DE ALUMNOS EN LABORATORIOS</td>
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

    {{-- TABLA PRINCIPAL DEL REPORTE --}}
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 25%;">Alumno (Matrícula)</th>
                <th style="width: 35%;">Laboratorios Visitados</th>
                <th style="width: 13%;">Clases asistidas</th>
                <th style="width: 13%;">Uso Libre</th>
                <th style="width: 14%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporteAlumnos as $user_id => $asistencias)
                @php
                    $alumno = $asistencias->first()->user;
                    $clases = $asistencias
                        ->where('tipo', 'Clase')
                        ->whereIn('estado', ['presente', 'justificado', null])
                        ->count();
                    $usoLibre = $asistencias->where('tipo', 'Uso Libre')->count();
                    $labs = $asistencias->pluck('centroComputo.nombre_centro')->unique()->filter();
                @endphp
                <tr>
                    <td>
                        <strong>{{ strtoupper($alumno->name . ' ' . $alumno->apellido_paterno . ' ' . $alumno->apellido_materno) }}</strong><br>
                        <small style="color: #555;">{{ $alumno->matricula }}</small>
                    </td>
                    <td>
                        @foreach ($labs as $lab)
                            <span class="badge">{{ strtoupper($lab) }}</span>
                        @endforeach
                    </td>
                    <td class="text-center">{{ $clases }}</td>
                    <td class="text-center">{{ $usoLibre }}</td>
                    <td class="text-center"><strong>{{ $clases + $usoLibre }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
