<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Horario de Prácticas de Laboratorio</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Letra pequeña para que todo encaje */
            margin: 0;
            padding: 0;
            color: #000;
        }

        /* Utilidad para forzar el salto de página en PDFs */
        .page-break {
            page-break-after: always;
        }

        .page-break:last-child {
            page-break-after: auto;
            /* Evita una página en blanco al final */
        }

        /* Tabla de Encabezado Institucional */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
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

        /* Tabla de Horarios */
        .horario-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            text-align: center;
        }

        .horario-table th,
        .horario-table td {
            border: 1px solid #000;
            padding: 4px;
            word-wrap: break-word;
        }

        .horario-table th {
            background-color: #1b4478;
            /* Azul oscuro institucional de la foto */
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            padding: 6px;
        }

        .col-hora {
            width: 90px;
            font-weight: bold;
        }

        .celda-vacia {
            color: #a0a0a0;
            font-style: italic;
        }

        .contenido-clase {
            font-size: 9px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        /* Ajustes de logo e imágenes de pie de página (Opcional, puedes poner rutas absolutas si las tienes) */
        .logo-marca {
            width: 70px;
        }

        /*
         * Para que cada laboratorio quepa en UNA hoja.
         *
         * El controlador prueba estos ajustes en orden, del más holgado al más
         * apretado, y se queda con el primero con el que ese laboratorio ocupa una
         * sola hoja. El 0 no cambia nada: es el diseño de siempre. Un laboratorio con
         * pocas clases conserva su letra aunque otro del mismo PDF necesite reducirla.
         */
        .ajuste-1 .horario-table td { padding: 3px; }
        .ajuste-1 .contenido-clase { font-size: 8.5px; }

        .ajuste-2 .horario-table td { padding: 2px 3px; }
        .ajuste-2 .horario-table th { padding: 5px; }
        .ajuste-2 .contenido-clase { font-size: 8px; }

        .ajuste-3 .horario-table td { padding: 2px; }
        .ajuste-3 .horario-table th { padding: 4px; }
        .ajuste-3 .contenido-clase { font-size: 7.5px; line-height: 1.15; }

        .ajuste-4 .horario-table td { padding: 1px 2px; }
        .ajuste-4 .horario-table th { padding: 3px; font-size: 10px; }
        .ajuste-4 .contenido-clase { font-size: 7px; line-height: 1.1; }
        .ajuste-4 .header-table { margin-bottom: 6px; }

        .ajuste-5 .horario-table td { padding: 1px 2px; }
        .ajuste-5 .horario-table th { padding: 3px; font-size: 10px; }
        .ajuste-5 .contenido-clase { font-size: 6.5px; line-height: 1.1; }
        .ajuste-5 .header-table { margin-bottom: 6px; }

        .ajuste-6 .horario-table td { padding: 1px; }
        .ajuste-6 .horario-table th { padding: 2px; font-size: 9px; }
        .ajuste-6 .contenido-clase { font-size: 6px; line-height: 1.05; }
        .ajuste-6 .header-table { margin-bottom: 4px; }
        .ajuste-6 .header-table td { padding: 3px; }
    </style>
</head>

<body>

    @foreach ($datosPorCentro as $dato)
        <div class="page-break ajuste-{{ $dato['ajuste'] ?? 0 }}">

            {{-- ENCABEZADO INSTITUCIONAL --}}
            <table class="header-table">
                <tr>
                    <td rowspan="3" style="width: 15%; text-align: center;">
                        @php
                            // Asegúrate de poner el nombre y extensión correctos de tu imagen
                            $rutaLogo = public_path(config('marca.logo_documentos'));

                            // Verificamos que el archivo exista para que no marque error
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
                    <td style="width: 15%; font-weight: bold;">Versión:</td>
                    <td style="width: 15%;">{{ \Carbon\Carbon::now()->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="header-subtitle">HORARIO DE PRÁCTICAS DE LABORATORIO</td>
                    <td style="font-weight: bold;">Periodo de emisión:</td>
                    <td>
                        {{ strtoupper($semestreActivo->nombre) }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 10px;">
                        <b>Espacio:</b> {{ $dato['centro']->nombre_centro }}
                    </td>
                    <td style="font-weight: bold;">Página:</td>
                    <td>Página 1 de 1</td>
                </tr>
            </table>

            {{-- TABLA DEL HORARIO --}}
            <table class="horario-table">
                <thead>
                    <tr>
                        <th class="col-hora">HORARIO</th>
                        @foreach ($diasSemana as $dia)
                            <th>{{ strtoupper($dia) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($horasDisponibles as $hora)
                        @php
                            $horaFin = date('H:i', strtotime($hora . ' +1 hour'));
                        @endphp
                        <tr>
                            <td class="col-hora">{{ $hora }} - {{ $horaFin }}</td>

                            @foreach ($diasSemana as $dia)
                                @php
                                    $horario = $dato['mapa'][$dia][$hora] ?? null;
                                @endphp

                                <td>
                                    @if ($horario)
                                        @php
                                            $nombre = $horario->user->name ?? '';
                                            $paterno = $horario->user->apellido_paterno ?? '';
                                            $inicial = $nombre ? substr($nombre, 0, 1) . '' : '';
                                            $profesor = strtoupper(trim($inicial . $paterno));

                                            $materia = strtoupper($horario->materia->nombre_materia);
                                            // Sólo la clave ("3KM"): el nombre del grupo repite la
                                            // materia, que ya va escrita justo antes.
                                            $grupo = strtoupper($horario->grupo->clave_corta);
                                        @endphp
                                        <div class="contenido-clase">
                                            {{ $profesor }}, {{ $materia }}, {{ $grupo }}
                                        </div>
                                    @else
                                        <span class="celda-vacia">...</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</body>

</html>
