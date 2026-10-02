<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AlumnosExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $reporteAlumnos;

    public function __construct($reporteAlumnos)
    {
        $this->reporteAlumnos = $reporteAlumnos;
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->reporteAlumnos as $id => $asistencias) {
            $alumno = $asistencias->first()->user;
            $data[] = [
                $alumno->matricula,
                $alumno->name . ' ' . $alumno->apellido_paterno . ' ' . $alumno->apellido_materno,
                $asistencias->pluck('centroComputo.nombre_centro')->unique()->implode(', '),
                $asistencias->where('tipo', 'Clase')->whereIn('estado', ['presente', 'justificado', null])->count(),
                $asistencias->where('tipo', 'Uso Libre')->count(),
                $asistencias->count()
            ];
        }
        return $data;
    }

    public function headings(): array
    {
        return ['Matrícula', 'Nombre Completo', 'Laboratorios', 'Asistencias Clase', 'Accesos Uso Libre', 'Total General'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]]];
    }
}
