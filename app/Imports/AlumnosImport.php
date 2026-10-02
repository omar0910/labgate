<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Grupo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlumnosImport implements ToCollection, WithHeadingRow
{
    /** El archivo puede traer la matrícula bajo cualquiera de estos encabezados. */
    const COLUMNAS_MATRICULA = ['matricula', 'no_de_control', 'numero_de_control'];

    protected $grupo;

    /** Matrículas del archivo que no existen en el sistema, para avisar al usuario. */
    public $errores = [];

    /** Alumnos que quedaron inscritos en el grupo. */
    public $inscritos = 0;

    /** Filas descartadas por venir sin matrícula. */
    public $ignorados = 0;

    public function __construct(Grupo $grupo)
    {
        $this->grupo = $grupo;
    }

    public function collection(Collection $rows)
    {
        $this->errores = [];

        // Si el archivo no trae ninguna columna de matrícula, se avisa en vez de
        // terminar "sin errores" pero sin haber inscrito a nadie.
        if ($rows->isNotEmpty()) {
            $encabezados = array_keys($rows->first()->toArray());

            if (empty(array_intersect(self::COLUMNAS_MATRICULA, $encabezados))) {
                throw new \Exception(
                    'El archivo no tiene una columna de matrícula en la primera fila. ' .
                    'Se esperaba MATRICULA o NO DE CONTROL. Revisa que sea el archivo correcto.'
                );
            }
        }

        // Se juntan primero todas las matrículas y luego se consultan de una sola vez,
        // en lugar de una consulta por renglón.
        $matriculas = [];
        $nombresPorMatricula = [];

        foreach ($rows as $row) {
            $matriculaRaw = $row['matricula'] ?? $row['no_de_control'] ?? $row['numero_de_control'] ?? null;
            $matricula = trim((string) $matriculaRaw);

            if ($matricula === '') {
                $this->ignorados++;
                continue;
            }

            $matriculas[$matricula] = true;
            $nombresPorMatricula[$matricula] = trim((string) ($row['nombre_del_estudiante'] ?? 'Desconocido'));
        }

        $matriculas = array_keys($matriculas);

        if (empty($matriculas)) {
            return;
        }

        // Sólo alumnos: una matrícula no debería inscribir a un profesor o a un administrador.
        $encontrados = User::whereIn('matricula', $matriculas)
            ->where('rol', 'Alumno')
            ->activos()
            ->pluck('id', 'matricula');

        // Los dados de baja no se inscriben solos: se avisan, para que se decida si
        // se reactivan (desde la lista de alumnos).
        $deBaja = User::whereIn('matricula', $matriculas)
            ->where('rol', 'Alumno')
            ->dadosDeBaja()
            ->pluck('id', 'matricula');

        foreach ($matriculas as $matricula) {
            if ($deBaja->has($matricula)) {
                $nombre = $nombresPorMatricula[$matricula] ?: 'Desconocido';
                $this->errores[] = "Matrícula: $matricula ($nombre) — está dado de baja";
                continue;
            }

            if (!$encontrados->has($matricula)) {
                $nombre = $nombresPorMatricula[$matricula] ?: 'Desconocido';
                $this->errores[] = "Matrícula: $matricula ($nombre)";
            }
        }

        if ($encontrados->isNotEmpty()) {
            // syncWithoutDetaching agrega sin sacar a los que ya estaban en el grupo.
            $this->grupo->alumnos()->syncWithoutDetaching($encontrados->values()->all());
            $this->inscritos = $encontrados->count();
        }
    }
}
