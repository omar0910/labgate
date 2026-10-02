<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta la pestaña "Resumen General" con los mismos bloques que se ven en
 * pantalla: cifras clave, uso por laboratorio, carreras atendidas,
 * cumplimiento docente y equipos con más uso.
 */
class ResumenGeneralExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function array(): array
    {
        $d = $this->datos;
        $export = [];

        // --- ENCABEZADO DEL DOCUMENTO ---
        $export[] = ['RESUMEN GENERAL DEL CENTRO DE CÓMPUTO'];
        $export[] = ['Periodo:', $d['semestre']->nombre ?? 'N/D'];
        $export[] = ['Fecha de generación:', \Carbon\Carbon::now()->format('d/m/Y')];
        $export[] = [];

        // --- TABLA 1: CIFRAS CLAVE ---
        $export[] = ['1. CIFRAS CLAVE DEL PERIODO'];
        $export[] = ['Indicador', 'Valor', 'Desglose'];

        $export[] = [
            'Accesos registrados',
            $d['totalAccesos'],
            'Clases: ' . $d['accesosClase'] . ' | Uso libre: ' . $d['accesosUsoLibre'],
        ];
        $export[] = [
            'Clases agendadas',
            $d['totalClases'],
            'Recurrentes: ' . $d['clasesFijas'] . ' (' . ($d['sesionesPorSemana'] ?? 0) . ' sesiones por semana)'
                . ' | Especiales: ' . $d['clasesEspeciales'],
        ];
        $export[] = [
            'Docentes activos',
            $d['docentesActivos'],
            'Con clase fija: ' . $d['docentesFijos'] . ' | Con reserva: ' . $d['docentesEspeciales'],
        ];
        $export[] = [
            'Cumplimiento de clases',
            $d['cumplimiento'] !== null ? $d['cumplimiento'] . '%' : 'N/D',
            $d['cumplimiento'] !== null
                ? $d['clasesCumplidas'] . ' de ' . $d['esperadasHoy'] . ' clases impartidas a la fecha de corte | Faltas: ' . $d['faltasTotales']
                : 'Aún no hay clases que debieran haberse impartido en este periodo',
        ];

        $export[] = [];
        $export[] = [];

        // --- TABLA 2: USO POR LABORATORIO ---
        $export[] = ['2. USO POR LABORATORIO'];
        $export[] = ['Laboratorio', 'Sesiones de Clase', 'Accesos Uso Libre', 'Total', 'Hora Pico'];

        foreach ($d['reporteLaboratorios'] as $lab) {
            $export[] = [
                $lab['nombre'],
                $lab['sesiones'],
                $lab['uso_libre'],
                $lab['total_impacto'],
                $lab['hora_pico'],
            ];
        }

        $export[] = [];
        $export[] = [];

        // --- TABLA 3: CARRERAS ATENDIDAS ---
        $export[] = ['3. CARRERAS ATENDIDAS (ALUMNOS DISTINTOS)'];
        $export[] = ['Programa Educativo', 'Alumnos Únicos', 'Porcentaje del Total'];

        if (count($d['reporteCarreras']) > 0) {
            foreach ($d['reporteCarreras'] as $c) {
                $export[] = [
                    $c['carrera'],
                    $c['cantidad'],
                    $c['porcentaje'] . '%',
                ];
            }
        } else {
            $export[] = ['No hay alumnos registrados en el periodo', '', ''];
        }

        $export[] = [];
        $export[] = [];

        // --- TABLA 4: CUMPLIMIENTO POR DOCENTE ---
        $export[] = ['4. DOCENTES POR DEBAJO DEL 85% DE CUMPLIMIENTO'];
        $export[] = ['Docente', 'Impartidas', 'Esperadas a la Fecha', 'Faltas', 'Justificadas', '% Cumplimiento'];

        if (count($d['docentesAtencion']) > 0) {
            foreach ($d['docentesAtencion'] as $doc) {
                $export[] = [
                    $doc['profesor'],
                    $doc['presentes'],
                    $doc['esperadas_hoy'],
                    $doc['faltas'],
                    $doc['justificadas'],
                    $doc['porcentaje'] . '%',
                ];
            }
        } else {
            $export[] = ['Ningún docente está por debajo del 85% en este periodo', '', '', '', '', ''];
        }

        $export[] = [];
        $export[] = [];

        // --- TABLA 5: EQUIPOS CON MÁS USO ---
        $export[] = ['5. EQUIPOS CON MÁS USO (CANDIDATOS A MANTENIMIENTO PREVENTIVO)'];
        $export[] = ['Equipo', 'Laboratorio', 'Usos Recientes', 'Último Mantenimiento', 'Estado'];

        foreach ($d['equiposDesgaste'] as $eq) {
            $export[] = [
                'PC #' . $eq->numero_maquina,
                $eq->centroComputo->nombre_centro ?? 'Sin laboratorio',
                $eq->usos_acumulados,
                $eq->ultimo_mantenimiento ? \Carbon\Carbon::parse($eq->ultimo_mantenimiento)->format('d/m/Y') : 'N/A',
                ucfirst($eq->estado),
            ];
        }

        return $export;
    }

    public function styles(Worksheet $sheet)
    {
        // Mismo criterio de estilo que los demás reportes del sistema:
        // título en verde institucional y encabezado de tabla en fondo verde.
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => '009B4D']]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => '009B4D']]],
            6 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => '009B4D']],
            ],
        ];
    }
}
