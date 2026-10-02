<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CarrerasDetalleExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $export = [];

        // ENCABEZADO
        $export[] = ['REPORTE ANALÍTICO DETALLADO DE DEMANDA POR CARRERA'];
        $export[] = ['Periodo:', $this->datos['semestre']->nombre ?? 'N/A', 'Total Únicos:', $this->datos['totalAlumnosUnicos']];
        $export[] = [];

        // SECCIÓN GLOBAL
        $export[] = ['IMPACTO INSTITUCIONAL (GLOBAL)'];
        $export[] = ['#', 'Programa Educativo', 'Estudiantes Únicos', 'Porcentaje del Total'];

        $i = 1;
        foreach ($this->datos['reporteCarreras'] as $dato) {
            $export[] = [$i++, $dato['carrera'], $dato['cantidad'], $dato['porcentaje'] . '%'];
        }

        $export[] = [];
        $export[] = [];

        // SECCIÓN POR LABORATORIO
        $export[] = ['DESGLOSE DETALLADO POR LABORATORIO'];

        foreach ($this->datos['reporteLaboratorios'] as $lab) {
            $export[] = [$lab['nombre'] . ' (Total: ' . $lab['total_alumnos'] . ' alumnos)'];
            $export[] = ['Programa Educativo', 'Estudiantes', 'Porcentaje Interno'];

            if (empty($lab['carreras'])) {
                $export[] = ['Sin registros', '0', '0%'];
            } else {
                foreach ($lab['carreras'] as $carrera) {
                    $export[] = [$carrera['carrera'], $carrera['cantidad'], $carrera['porcentaje'] . '%'];
                }
            }
            $export[] = []; // Espacio entre laboratorios
        }

        return $export;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => '009B4D']]],
            4 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]],
        ];
    }
}
