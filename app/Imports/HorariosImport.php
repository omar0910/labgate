<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;

/**
 * Lee el archivo de horarios del plantel y lo deja en una lista de clases.
 *
 * El archivo es una rejilla: una fila por cada hora de cada día de cada área,
 * y la mayoría vacías. Una clase de dos horas ocupa dos filas seguidas, así que
 * aquí se vuelven a unir en un solo bloque de 13:00 a 15:00.
 *
 * Esta clase NO toca la base de datos: sólo entrega lo que dice el archivo. De
 * decidir a qué laboratorio, materia y docente corresponde cada cosa se encarga
 * PlanDeHorarios, y de guardarlo, el controlador, cuando la persona confirma.
 */
class HorariosImport implements ToCollection, WithEvents
{
    /** Cómo puede venir escrito cada encabezado que hace falta. */
    const COLUMNAS = [
        'area'    => ['area', 'laboratorio', 'centro', 'centro_de_computo'],
        'dia'     => ['dia', 'dias'],
        'inicio'  => ['inicio', 'hora_inicio', 'hora_de_inicio', 'entrada'],
        'termino' => ['termino', 'fin', 'hora_fin', 'hora_de_termino', 'salida'],
        'docente' => ['docente', 'profesor', 'personal_academico', 'maestro'],
        'materia' => ['materia', 'asignatura', 'nombre_de_la_asignatura', 'nombre_de_la_materia'],
        'grupo'   => ['grupo', 'grupos'],
    ];

    /** Sin estas columnas la hoja no sirve. */
    const OBLIGATORIAS = ['dia', 'inicio', 'termino', 'materia', 'grupo'];

    /** Filas que se revisan al principio de cada hoja buscando el encabezado. */
    const FILAS_PARA_ENCABEZADO = 15;

    /** La inicial con la que el archivo escribe cada día. */
    const DIAS = [
        'L' => 'Lunes',
        'M' => 'Martes',
        'N' => 'Miércoles',
        'J' => 'Jueves',
        'V' => 'Viernes',
        'S' => 'Sábado',
        'D' => 'Domingo',
    ];

    /** Clases encontradas, ya con sus horas unidas. */
    public $clases = [];

    /** Filas con datos incompletos, para avisar sin detener todo. */
    public $problemas = [];

    /** Hojas en las que sí se encontraron las columnas. */
    public $hojasLeidas = 0;

    /** Nombre de la hoja que se está leyendo (sirve de área si no hay columna). */
    protected $hoja = '';

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->hoja = $event->getSheet()->getTitle();
            },
        ];
    }

    public function collection(Collection $rows)
    {
        $columnas = $this->buscarEncabezado($rows);

        if (! $columnas) {
            return;
        }

        $this->hojasLeidas++;

        $bloques = [];

        foreach ($rows as $numero => $row) {
            if ($numero <= $columnas['fila']) {
                continue;
            }

            $fila = $row->values();
            $leer = function ($campo) use ($fila, $columnas) {
                return isset($columnas[$campo]) ? trim((string) ($fila[$columnas[$campo]] ?? '')) : '';
            };

            $materia = $leer('materia');
            $grupo   = $leer('grupo');

            // La rejilla viene casi toda vacía: son las horas libres.
            if ($materia === '' && $grupo === '') {
                continue;
            }

            $area    = $leer('area') !== '' ? $leer('area') : $this->hoja;
            $dia     = $this->traducirDia($leer('dia'));
            $inicio  = $this->aHora($leer('inicio'));
            $termino = $this->aHora($leer('termino'));
            $docente = $leer('docente');

            if ($materia === '' || $grupo === '' || $dia === null || $inicio === null || $termino === null) {
                $this->problemas[] = [
                    'hoja'    => $this->hoja,
                    'fila'    => $numero + 1,
                    'detalle' => $this->describirFaltante($materia, $grupo, $dia, $inicio, $termino),
                ];
                continue;
            }

            $bloques[] = [
                'area'    => $area,
                'dia'     => $dia,
                'inicio'  => $inicio,
                'fin'     => $termino,
                'docente' => $docente,
                'materia' => $materia,
                'grupo'   => $grupo,
                'hoja'    => $this->hoja,
            ];
        }

        $this->clases = array_merge($this->clases, $this->unirHorasSeguidas($bloques));
    }

    /**
     * Une las horas seguidas de una misma clase en un solo bloque.
     *
     * 13:00-14:00 y 14:00-15:00 del mismo grupo, materia y docente, el mismo día
     * y en el mismo laboratorio, son una clase de 13:00 a 15:00.
     */
    protected function unirHorasSeguidas(array $bloques): array
    {
        usort($bloques, function ($a, $b) {
            return [$a['area'], $a['dia'], $a['grupo'], $a['materia'], $a['inicio']]
               <=> [$b['area'], $b['dia'], $b['grupo'], $b['materia'], $b['inicio']];
        });

        $unidos = [];

        foreach ($bloques as $bloque) {
            $anterior = end($unidos);

            $mismaClase = $anterior
                && $anterior['area']    === $bloque['area']
                && $anterior['dia']     === $bloque['dia']
                && $anterior['grupo']   === $bloque['grupo']
                && $anterior['materia'] === $bloque['materia']
                && $anterior['docente'] === $bloque['docente']
                && $anterior['fin']     === $bloque['inicio'];

            if ($mismaClase) {
                $unidos[count($unidos) - 1]['fin'] = $bloque['fin'];
                continue;
            }

            $unidos[] = $bloque;
        }

        return $unidos;
    }

    /** Busca en las primeras filas una que sirva de encabezado. */
    protected function buscarEncabezado(Collection $rows): ?array
    {
        foreach ($rows as $numero => $row) {
            if ($numero >= self::FILAS_PARA_ENCABEZADO) {
                break;
            }

            $encontradas = [];

            foreach ($row->values() as $columna => $valor) {
                $titulo = Str::slug((string) $valor, '_');

                foreach (self::COLUMNAS as $campo => $nombres) {
                    if (! isset($encontradas[$campo]) && in_array($titulo, $nombres, true)) {
                        $encontradas[$campo] = $columna;
                    }
                }
            }

            if (empty(array_diff(self::OBLIGATORIAS, array_keys($encontradas)))) {
                return $encontradas + ['fila' => $numero];
            }
        }

        return null;
    }

    /** "L" -> "Lunes". Devuelve null si no se reconoce. */
    protected function traducirDia(string $valor): ?string
    {
        $limpio = mb_strtoupper(trim($valor), 'UTF-8');

        if (isset(self::DIAS[$limpio])) {
            return self::DIAS[$limpio];
        }

        // Por si algún archivo escribe el día completo.
        foreach (self::DIAS as $nombre) {
            if (\App\Support\Emparejador::normalizar($nombre) === \App\Support\Emparejador::normalizar($limpio)) {
                return $nombre;
            }
        }

        return null;
    }

    /** "07" -> "07:00:00". Acepta también "7:00" y la hora como número de Excel. */
    protected function aHora(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})$/', $valor, $p)) {
            $hora = (int) $p[1];
            return $hora <= 23 ? sprintf('%02d:00:00', $hora) : null;
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})/', $valor, $p)) {
            return sprintf('%02d:%02d:00', (int) $p[1], (int) $p[2]);
        }

        // Excel guarda las horas como fracción del día: 0.291666 = 07:00.
        if (is_numeric($valor) && (float) $valor > 0 && (float) $valor < 1) {
            $minutos = (int) round(((float) $valor) * 24 * 60);
            return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
        }

        return null;
    }

    protected function describirFaltante(string $materia, string $grupo, ?string $dia, ?string $inicio, ?string $fin): string
    {
        $faltan = [];

        if ($materia === '') { $faltan[] = 'la materia'; }
        if ($grupo === '')   { $faltan[] = 'el grupo'; }
        if ($dia === null)   { $faltan[] = 'el día'; }
        if ($inicio === null || $fin === null) { $faltan[] = 'la hora'; }

        return 'No se entendió ' . implode(', ', $faltan) . '.';
    }
}
