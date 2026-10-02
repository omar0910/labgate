<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MateriasDetalleExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $export = [];
        $export[] = ['AUDITORÍA DE RENDIMIENTO POR ASIGNATURA'];
        $export[] = ['Periodo:', $this->datos['semestre']->nombre ?? 'N/A'];
        $export[] = ['Fecha de reporte:', date('d/m/Y h:i A')];
        $export[] = []; // Espacio en blanco antes de la tabla

        // Definimos los encabezados
        $export[] = [
            'ASIGNATURA',
            'GRUPO',
            'DOCENTE',
            'LABORATORIO',
            'PROG. (HOY / TOTAL)',
            'CLASES IMPARTIDAS',
            'FALTAS DOCENTE',
            'FALTAS JUSTIFICADAS',
            '% CUMPLIMIENTO',
            'PROMEDIO ASISTENCIA',
            'TOTAL ALUMNOS (GRUPO)'
        ];

        foreach ($this->datos['reporteMaterias'] as $item) {
            $export[] = [
                $item['materia'],
                $item['grupo'],
                $item['docente'],
                $item['laboratorio'],
                $item['esperadas_hoy'] . ' / ' . $item['esperadas_total'],
                $item['impartidas'],
                $item['faltas'],
                $item['justificadas'],
                $item['esperadas_hoy'] > 0 ? $item['porcentaje_cumplimiento'] . '%' : 'N/A',
                $item['asistencia_promedio'],
                $item['alumnos_unicos']
            ];
        }

        return $export;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el título principal
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => '009B4D']]],
            // Como quitamos la nota, los encabezados de la tabla ahora están en la Fila 5
            5 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]
            ],
        ];
    }
}
