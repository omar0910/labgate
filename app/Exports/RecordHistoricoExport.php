<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecordHistoricoExport implements FromArray, ShouldAutoSize, WithStyles
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
        $export[] = ['RÉCORD HISTÓRICO DE EQUIPOS (CARGA DE TRABAJO)'];
        $export[] = ['Periodo:', $this->datos['semestre']->nombre ?? 'N/A'];
        $export[] = [];

        // SECCIÓN GLOBAL
        $export[] = ['1. RANKING INSTITUCIONAL (GLOBAL)'];
        $export[] = ['Rango', 'Equipo', 'Ubicación', 'Total Sesiones', 'Horas de Uso'];

        $i = 1;
        foreach ($this->datos['recordGlobal'] as $hist) {
            $horas = floor($hist->total_minutos / 60);
            $minutos = $hist->total_minutos % 60;

            $export[] = [
                $i++,
                'PC #' . $hist->numero_maquina,
                $hist->centroComputo->nombre_centro ?? 'N/A',
                $hist->total_usos,
                $horas . 'h ' . $minutos . 'm'
            ];
        }

        $export[] = [];
        $export[] = [];

        // SECCIÓN POR LABORATORIO
        $export[] = ['2. DESGLOSE DETALLADO POR LABORATORIO'];

        foreach ($this->datos['recordLaboratorios'] as $nombreLab => $equiposLab) {
            $export[] = [$nombreLab];
            $export[] = ['Equipo', 'Total Sesiones', 'Horas de Uso'];

            if ($equiposLab->isEmpty()) {
                $export[] = ['Sin registros', '0', '0h 0m'];
            } else {
                foreach ($equiposLab as $hist) {
                    $horas = floor($hist->total_minutos / 60);
                    $minutos = $hist->total_minutos % 60;

                    $export[] = [
                        'PC #' . $hist->numero_maquina,
                        $hist->total_usos,
                        $horas . 'h ' . $minutos . 'm'
                    ];
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
