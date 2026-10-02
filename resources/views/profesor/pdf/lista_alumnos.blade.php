<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Lista de Alumnos - {{ mb_strtoupper($horario->materia->nombre_materia, 'UTF-8') }}</title>
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
            padding: 4px 2px;
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

        .text-uppercase {
            text-transform: uppercase;
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
            <td class="header-subtitle">REPORTE OFICIAL DE ALUMNOS INSCRITOS</td>
            <td style="font-weight: bold;">Periodo de emisión:</td>
            <td>
                {{ strtoupper($semestre->nombre ?? 'N/A') }}
            </td>
        </tr>
        <tr>
            <td style="text-align: left; padding-left: 10px;">
                <b>Alcance:</b> Lista de Asistencia
            </td>
            <td style="font-weight: bold;">Documento:</td>
            <td>Reporte General</td>
        </tr>
    </table>

    {{-- CAJA DE INFORMACIÓN DE MATERIA Y PROFESOR --}}
    <div class="summary-box">
        <table>
            <tr>
                <td style="width: 15%;"><strong>MATERIA:</strong></td>
                <td style="width: 50%;" class="text-uppercase">
                    {{ mb_strtoupper($horario->materia->nombre_materia, 'UTF-8') }}</td>
                <td style="width: 15%;"><strong>GRUPO:</strong></td>
                <td style="width: 20%;" class="text-uppercase">
                    {{ mb_strtoupper($horario->grupo->nombre_grupo, 'UTF-8') }}</td>
            </tr>
            <tr>
                <td><strong>PROFESOR:</strong></td>
                @php
                    $nombreCompleto = trim(
                        ($horario->user->name ?? '') .
                            ' ' .
                            ($horario->user->apellido_paterno ?? '') .
                            ' ' .
                            ($horario->user->apellido_materno ?? ''),
                    );
                @endphp
                <td class="text-uppercase">{{ mb_strtoupper($nombreCompleto, 'UTF-8') }}</td>
                <td><strong>FECHA EMISIÓN:</strong></td>
                <td>{{ date('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    {{-- TABLA PRINCIPAL DEL REPORTE --}}
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Matrícula</th>
                <th style="width: 40%;">Nombre del Alumno</th>
                <th style="width: 25%;">Carrera</th>
                <th style="width: 15%;">Firma / Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($alumnos as $index => $alumno)
                @php
                    $nombreAlumno = trim(
                        ($alumno->apellido_paterno ?? '') .
                            ' ' .
                            ($alumno->apellido_materno ?? '') .
                            ' ' .
                            $alumno->name,
                    );
                @endphp
                <tr>
                    <td class="text-center fw-bold">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $alumno->matricula ?? 'S/M' }}</td>
                    <td class="text-uppercase">{{ mb_strtoupper($nombreAlumno, 'UTF-8') }}</td>
                    <td class="text-uppercase" style="font-size: 9px;">
                        {{ mb_strtoupper($alumno->carrera ?? 'SIN ASIGNAR', 'UTF-8') }}
                    </td>
                    <td></td> {{-- Columna vacía para que el profe anote o firme --}}
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
