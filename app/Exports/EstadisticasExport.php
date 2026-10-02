<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EstadisticasExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $alumnosData;

    public function __construct(array $alumnosData)
    {
        $this->alumnosData = $alumnosData;
    }

    public function array(): array
    {
        $data = [];
        $contador = 1;

        foreach ($this->alumnosData as $alumno) {
            $data[] = [
                $contador++,
                $alumno['matricula'],
                $alumno['nombre'],
                $alumno['asistencias'],
                $alumno['faltas'],
                $alumno['porcentaje'] . '%',
                $alumno['estado']
            ];
        }
        return $data;
    }

    public function headings(): array
    {
        return [
            '#',
            'Matrícula',
            'Nombre del Alumno',
            'Asistencias',
            'Faltas',
            '% Progreso',
            'Estado'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]
            ],
        ];
    }
}
