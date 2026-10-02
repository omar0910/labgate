<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsoLibreDetalleExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $export = [];
        $export[] = ['BITÁCORA DE USO LIBRE - ' . ($this->datos['semestre']->nombre ?? 'N/A')];
        $export[] = ['Reporte generado el:', date('d/m/Y h:i A')];
        $export[] = []; // Espacio

        $export[] = ['FOLIO', 'FECHA', 'MATRÍCULA', 'NOMBRE COMPLETO', 'LABORATORIO', 'PC #', 'HORA ENTRADA', 'HORA SALIDA', 'DURACIÓN (MIN)'];

        foreach ($this->datos['registros'] as $reg) {
            $entrada = \Carbon\Carbon::parse($reg->fecha_hora_registro);
            $salida = $reg->fecha_hora_salida ? \Carbon\Carbon::parse($reg->fecha_hora_salida) : null;

            $export[] = [
                str_pad($reg->id, 5, '0', STR_PAD_LEFT),
                \Carbon\Carbon::parse($reg->fecha)->format('d/m/Y'),
                $reg->user->matricula ?? 'S/M',
                $reg->user->name . ' ' . $reg->user->apellido_paterno . ' ' . $reg->user->apellido_materno,
                $reg->centroComputo->nombre_centro,
                $reg->numero_maquina,
                $entrada->format('h:i A'),
                $salida ? $salida->format('h:i A') : 'En curso',
                $salida ? $entrada->diffInMinutes($salida) : 0
            ];
        }

        return $export;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => '009B4D']]],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]
            ],
        ];
    }
}
