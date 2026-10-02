<?php

namespace App\Imports;

use App\Models\Materia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MateriasImport implements ToCollection, WithHeadingRow
{
    /** Columnas que el archivo debe traer para que la importación tenga sentido. */
    const COLUMNAS = ['clave', 'nombre_de_la_asignatura'];

    /** Materias que se dieron de alta. */
    public $creadas = 0;

    /** Materias que ya existían y se actualizaron. */
    public $actualizadas = 0;

    /** Filas descartadas por venir sin clave o sin nombre. */
    public $ignoradas = 0;

    /** Claves ya tratadas en este archivo, para no contar dos veces la misma materia. */
    protected $vistas = [];

    public function collection(Collection $rows)
    {
        // Si el archivo no trae las columnas esperadas, se avisa en vez de terminar
        // "sin errores" pero sin haber hecho nada, que es lo que despista al usuario.
        if ($rows->isNotEmpty()) {
            $encabezados = array_keys($rows->first()->toArray());
            $faltantes = array_diff(self::COLUMNAS, $encabezados);

            if (!empty($faltantes)) {
                throw new \Exception(
                    'El archivo no tiene las columnas necesarias. Se esperaban CLAVE y ' .
                    'NOMBRE DE LA ASIGNATURA en la primera fila. Revisa que sea el archivo correcto.'
                );
            }
        }

        foreach ($rows as $row) {

            $clave  = trim((string) ($row['clave'] ?? ''));
            $nombre = trim((string) ($row['nombre_de_la_asignatura'] ?? ''));

            // El archivo del plantel trae filas de relleno entre carreras.
            if ($clave === '' || $nombre === '') {
                $this->ignoradas++;
                continue;
            }

            // El archivo de horarios repite la misma clave en cada grupo que la cursa,
            // así que se trata una sola vez y los totales cuentan materias, no renglones.
            $claveComparable = mb_strtoupper($clave);
            if (isset($this->vistas[$claveComparable])) {
                continue;
            }
            $this->vistas[$claveComparable] = true;

            // Los créditos se guardan tal cual vienen ("2--2--4"), como texto.
            $creditos = isset($row['creditos_satca']) ? trim((string) $row['creditos_satca']) : null;

            // Se busca por CLAVE: si ya existe se corrige el nombre, si no se da de alta.
            $materia = Materia::where('clave', $clave)->first();

            if ($materia) {
                $materia->update([
                    'nombre_materia' => $nombre,
                    'creditos'       => $creditos,
                ]);
                $this->actualizadas++;
            } else {
                Materia::create([
                    'clave'          => $clave,
                    'nombre_materia' => $nombre,
                    'creditos'       => $creditos,
                ]);
                $this->creadas++;
            }
        }
    }
}
