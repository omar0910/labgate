<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocentesExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $data = [];
        $data[] = ['REPORTE DE DOCENTES Y ASISTENCIA'];
        $data[] = ['Periodo:', $this->datos['semestre']->nombre ?? 'N/A'];
        $data[] = ['Fecha de reporte:', date('d/m/Y h:i A')];
        $data[] = []; // Espacio

        // Encabezados de la Tabla
        $data[] = [
            'DOCENTE',
            'MATERIAS ASIGNADAS',
            'CLASES PROGRAMADAS (HOY / TOTAL)',
            'ASISTENCIAS',
            'FALTAS',
            'JUSTIFICADAS',
            '% CUMPLIMIENTO'
        ];

        // Llenado de los datos (Usando los datos pre-calculados del controlador)
        foreach ($this->datos['reporteDocentes'] as $docente) {
            $data[] = [
                $docente['profesor'],
                implode(', ', $docente['materias']->toArray()),
                $docente['esperadas_hoy'] . ' / ' . $docente['esperadas_total'],
                $docente['presentes'],
                $docente['faltas'],
                $docente['justificadas'],
                $docente['porcentaje'] . '%'
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el Título Principal (Fila 1)
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => '009B4D']]
            ],

            // Estilos para los Encabezados de la Tabla (Fila 5)
            5 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]
            ],
        ];
    }
}
