<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaboratoriosExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $export = [];

        // --- TABLA 1: IMPACTO DE SESIONES ---
        $export[] = ['REPORTE DE UTILIZACIÓN DE LABORATORIOS'];
        $export[] = ['Laboratorio', 'Sesiones de Clase', 'Con Equipo Personal', 'Accesos Uso Libre', 'Impacto Total', 'Hora de Mayor Tráfico'];

        foreach ($this->datos['reporteLaboratorios'] as $lab) {
            $export[] = [
                $lab['nombre'],
                $lab['sesiones'],
                $lab['equipo_personal'] ?? 0,   // de las sesiones de clase, con la laptop del alumno
                $lab['uso_libre'],
                $lab['total_impacto'],
                $lab['hora_pico']
            ];
        }

        $export[] = []; // Espacio en blanco
        $export[] = []; // Espacio en blanco

        // --- TABLA 2: RÉCORD HISTÓRICO SEMESTRAL ---
        $export[] = ['RÉCORD HISTÓRICO SEMESTRAL'];
        $export[] = ['Equipo', 'Laboratorio', 'Total de Sesiones', 'Horas Totales de Uso'];

        foreach ($this->datos['topMaquinasHistorico'] as $hist) {
            $horas = floor($hist->total_minutos / 60);
            $minutos = $hist->total_minutos % 60;

            $export[] = [
                'PC #' . $hist->numero_maquina,
                $hist->centroComputo->nombre_centro ?? 'N/A',
                $hist->total_usos,
                $horas . 'h ' . $minutos . 'm'
            ];
        }

        $export[] = []; // Espacio en blanco
        $export[] = []; // Espacio en blanco

        // --- TABLA 3: REGISTRO FÍSICO DE EQUIPOS ---
        $export[] = ['REGISTRO FÍSICO DE EQUIPOS Y MANTENIMIENTO'];
        $export[] = ['Equipo', 'Laboratorio', 'Usos Recientes (Desde último mtto)', 'Vida Útil (Histórico)', 'Último Mantenimiento', 'Estado Actual'];

        foreach ($this->datos['equiposDesgaste'] as $equipo) {
            $export[] = [
                'PC #' . $equipo->numero_maquina,
                $equipo->centroComputo->nombre_centro ?? 'N/A',
                $equipo->usos_acumulados,
                $equipo->usos_historicos,
                $equipo->ultimo_mantenimiento ? \Carbon\Carbon::parse($equipo->ultimo_mantenimiento)->format('d/m/Y') : 'N/A',
                ucfirst($equipo->estado)
            ];
        }

        $export[] = []; // Espacio en blanco

        // --- NUEVA TABLA 4: ANÁLISIS GLOBAL POR CARRERA ---
        $export[] = ['ANÁLISIS DE ALCANCE GLOBAL POR CARRERA (ESTUDIANTES ÚNICOS)'];
        $export[] = ['Carrera', 'Total Alumnos Únicos', 'Porcentaje de Alcance'];

        foreach ($this->datos['reporteCarreras'] as $dato) {
            $export[] = [
                $dato['carrera'],
                $dato['cantidad'],
                $dato['porcentaje'] . '%'
            ];
        }

        $export[] = []; // Espacio en blanco
        $export[] = []; // Espacio en blanco

        // --- NUEVA TABLA 5: PREFERENCIA DE CARRERA POR LABORATORIO ---
        $export[] = ['PREFERENCIA DE CARRERA POR LABORATORIO (TOP 3 ÚNICOS)'];
        $export[] = ['Laboratorio', 'Carrera Top 1', 'Carrera Top 2', 'Carrera Top 3'];

        foreach ($this->datos['reporteLaboratorios'] as $lab) {
            $filaLab = [$lab['nombre']];

            // Recorremos el top_carreras que inyectamos en el controlador
            foreach ($lab['top_carreras'] as $tc) {
                $filaLab[] = $tc['carrera'] . ' (' . $tc['cantidad'] . ' alumnos)';
            }

            // Si hay menos de 3 carreras, rellenamos las celdas vacías
            while (count($filaLab) < 4) {
                $filaLab[] = 'N/A';
            }

            $export[] = $filaLab;
        }

        return $export;
    }

    public function styles(Worksheet $sheet)
    {
        // Nota: Los estilos por número de fila son manuales en FromArray.
        // Aquí mantenemos tus estilos originales.
        return [
            1 => ['font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => '009B4D']]],
            2 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']]
            ],
        ];
    }
}
