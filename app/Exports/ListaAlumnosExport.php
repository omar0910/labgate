<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListaAlumnosExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $alumnos;
    protected $contador = 0;

    // Recibimos los alumnos desde el controlador
    public function __construct($alumnos)
    {
        $this->alumnos = $alumnos;
    }

    public function collection()
    {
        return $this->alumnos;
    }

    // Nombres de las columnas en la primera fila
    public function headings(): array
    {
        return [
            '#',
            'Matrícula',
            'Nombre del Alumno',
            'Carrera',
            'Firma / Asistencia'
        ];
    }

    // Mapeamos los datos de cada alumno a las columnas correspondientes
    public function map($alumno): array
    {
        $this->contador++;
        $nombreCompleto = trim(($alumno->apellido_paterno ?? '') . ' ' . ($alumno->apellido_materno ?? '') . ' ' . $alumno->name);

        return [
            $this->contador,
            $alumno->matricula ?? 'S/M',
            mb_strtoupper($nombreCompleto, 'UTF-8'),
            mb_strtoupper($alumno->carrera ?? 'Sin asignar', 'UTF-8'),
            '' // Celda vacía para firma
        ];
    }

    // Le damos estilo a la tabla (Ej. Encabezado verde con letras blancas)
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
