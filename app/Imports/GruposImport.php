<?php

namespace App\Imports;

use App\Models\Grupo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Crea los grupos del semestre a partir del archivo de horarios del plantel.
 *
 * De cada fila sólo hacen falta dos datos, el grupo y la asignatura, y con ellos
 * se arma el nombre con el formato acordado:  1SM-Cálculo Diferencial
 *
 * El archivo del plantel cambia de formato de un semestre a otro: a veces la
 * columna se llama NOMBRE DE LA ASIGNATURA y a veces MATERIA, el encabezado no
 * siempre está en la primera fila y los datos pueden venir en cualquiera de las
 * hojas del libro. Por eso aquí no se exige un formato exacto: se busca en cada
 * hoja una fila que sirva de encabezado y de ahí se leen las dos columnas.
 *
 * También se repiten filas a propósito en esos archivos (el horario trae una
 * línea por cada hora de clase), así que la misma combinación puede venir muchas
 * veces; se registra una sola.
 */
class GruposImport implements ToCollection
{
    /** Cómo puede venir escrito el encabezado del grupo. */
    const COLUMNAS_GRUPO = ['grupo', 'grupos', 'clave_grupo', 'clave_de_grupo'];

    /** Cómo puede venir escrito el encabezado de la asignatura. */
    const COLUMNAS_MATERIA = [
        'nombre_de_la_asignatura',
        'nombre_de_la_materia',
        'nombre_asignatura',
        'nombre_materia',
        'asignatura',
        'materia',
    ];

    /** Filas que se revisan al principio de cada hoja buscando el encabezado. */
    const FILAS_PARA_ENCABEZADO = 15;

    /** Semestre al que se asignarán los grupos (siempre el activo). */
    protected $semestreId;

    /** Nombres ya existentes, para no consultarlos fila por fila. */
    protected $registrados;

    /** Combinaciones ya tomadas en esta importación (el archivo las repite). */
    protected $vistos = [];

    /** Grupos nuevos que se registraron. */
    public $creados = 0;

    /** Grupos del archivo que ya estaban registrados en el semestre. */
    public $repetidos = 0;

    /** Filas descartadas por venir sin grupo o sin asignatura. */
    public $ignorados = 0;

    /** Hojas del libro en las que sí se encontraron las columnas. */
    public $hojasLeidas = 0;

    public function __construct($semestreId)
    {
        $this->semestreId = $semestreId;
    }

    public function collection(Collection $rows)
    {
        $encabezado = $this->buscarEncabezado($rows);

        // Los libros del plantel traen hojas de resumen, de datos sueltos o
        // vacías. Las que no sirven se saltan en silencio; si al final no sirvió
        // ninguna, quien llama lo detecta con $hojasLeidas.
        if (! $encabezado) {
            return;
        }

        $this->hojasLeidas++;
        $this->cargarRegistrados();

        $porInsertar = [];
        $ahora = now();

        foreach ($rows as $numero => $row) {
            if ($numero <= $encabezado['fila']) {
                continue;
            }

            $fila = $row->values();
            $grupo      = trim((string) ($fila[$encabezado['grupo']] ?? ''));
            $asignatura = trim((string) ($fila[$encabezado['materia']] ?? ''));

            // En el horario, la mayoría de las filas son horas libres: vienen sin
            // materia y sin grupo.
            if ($grupo === '' || $asignatura === '') {
                $this->ignorados++;
                continue;
            }

            // El formato acordado con el Centro de Cómputo.
            $nombreGrupo = $grupo . '-' . $asignatura;
            $comparable  = mb_strtolower($nombreGrupo);

            if (isset($this->registrados[$comparable])) {
                $this->repetidos++;
                continue;
            }

            // La misma clase ocupa varias horas: sale repetida en el archivo.
            if (isset($this->vistos[$comparable])) {
                continue;
            }

            $this->vistos[$comparable] = true;

            $porInsertar[] = [
                'nombre_grupo' => $nombreGrupo,
                'semestre_id'  => $this->semestreId,
                'created_at'   => $ahora,
                'updated_at'   => $ahora,
            ];

            $this->creados++;
        }

        // Se guardan por lotes para no saturar la base con cientos de inserciones sueltas.
        foreach (array_chunk($porInsertar, 200) as $lote) {
            Grupo::insert($lote);
        }
    }

    /**
     * Busca en las primeras filas de la hoja una que sirva de encabezado.
     *
     * Devuelve en qué fila está y en qué columna quedó cada dato, o null si esta
     * hoja no trae lo que hace falta.
     */
    protected function buscarEncabezado(Collection $rows): ?array
    {
        foreach ($rows as $numero => $row) {
            if ($numero >= self::FILAS_PARA_ENCABEZADO) {
                break;
            }

            $grupo = null;
            $materia = null;

            foreach ($row->values() as $columna => $valor) {
                $titulo = Str::slug((string) $valor, '_');

                if ($grupo === null && in_array($titulo, self::COLUMNAS_GRUPO, true)) {
                    $grupo = $columna;
                } elseif ($materia === null && in_array($titulo, self::COLUMNAS_MATERIA, true)) {
                    $materia = $columna;
                }
            }

            if ($grupo !== null && $materia !== null) {
                return ['fila' => $numero, 'grupo' => $grupo, 'materia' => $materia];
            }
        }

        return null;
    }

    /** Los grupos que ya tiene el semestre, en minúsculas para comparar. */
    protected function cargarRegistrados(): void
    {
        if ($this->registrados !== null) {
            return;
        }

        $this->registrados = Grupo::where('semestre_id', $this->semestreId)
            ->pluck('nombre_grupo')
            ->mapWithKeys(function ($nombre) {
                return [mb_strtolower(trim($nombre)) => true];
            })
            ->all();
    }
}
