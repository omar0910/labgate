<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HardwareDetalleExport implements FromArray, ShouldAutoSize, WithStyles
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
        $export[] = ['REGISTRO FÍSICO DETALLADO DE HARDWARE'];
        $export[] = ['Periodo:', $this->datos['semestre']->nombre ?? 'N/A', 'Total Equipos:', $this->datos['totalEquipos']];
        $export[] = [];

        // SECCIÓN GLOBAL
        $export[] = ['1. INVENTARIO INSTITUCIONAL (GLOBAL)'];
        $export[] = ['#', 'Equipo', 'Ubicación', 'Usos Recientes', 'Histórico Total', 'Último Mantenimiento', 'Estado'];

        $i = 1;
        foreach ($this->datos['equiposGlobal'] as $equipo) {
            $fechaMtto = $equipo->ultimo_mantenimiento ? \Carbon\Carbon::parse($equipo->ultimo_mantenimiento)->format('d/m/Y') : 'Sin registro';
            $export[] = [
                $i++,
                'PC #' . $equipo->numero_maquina,
                $equipo->centroComputo->nombre_centro ?? 'N/A',
                $equipo->usos_acumulados,
                $equipo->usos_historicos,
                $fechaMtto,
                ucfirst($equipo->estado)
            ];
        }

        $export[] = [];
        $export[] = [];

        // SECCIÓN POR LABORATORIO
        $export[] = ['2. DESGLOSE DETALLADO POR LABORATORIO'];

        foreach ($this->datos['equiposLaboratorios'] as $nombreLab => $equiposLab) {
            $export[] = [$nombreLab . ' (' . $equiposLab->count() . ' PCs)'];
            $export[] = ['Equipo', 'Usos Recientes', 'Histórico Total', 'Estado'];

            if ($equiposLab->isEmpty()) {
                $export[] = ['Sin registros', '0', '0', 'N/A'];
            } else {
                foreach ($equiposLab as $equipo) {
                    $export[] = [
                        'PC #' . $equipo->numero_maquina,
                        $equipo->usos_acumulados,
                        $equipo->usos_historicos,
                        ucfirst($equipo->estado)
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
