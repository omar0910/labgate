<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Padrón de alumnos: da de alta a los nuevos y actualiza a los que ya existen.
 *
 * El archivo cambia de formato según de dónde se saque: a veces la matrícula
 * viene como "Numero de Control" y a veces como "matricula", la carrera como
 * "Nombre Carrera" o "carrera", trae columnas que no se usan (la CURP) y el
 * encabezado no siempre está en la primera fila. Por eso no se exige un formato
 * exacto: en cada hoja se busca una fila que sirva de encabezado y se toma cada
 * dato de la columna que se llame como alguno de sus nombres conocidos.
 *
 * Lo que no viene en el archivo no se toca: si falta la columna de carrera, o
 * una celda está vacía, el alumno se queda con lo que ya tenía.
 */
class AlumnosMaestraImport implements ToCollection
{
    /** Cómo puede venir escrito cada encabezado (en minúsculas, sin acentos y con guion bajo). */
    const COLUMNAS = [
        'matricula' => ['numero_de_control', 'no_de_control', 'num_de_control', 'numero_control', 'no_control',
            'num_control', 'n_control', 'control', 'matricula', 'no_matricula', 'numero_de_matricula'],
        'nombre'    => ['nombre', 'nombres', 'nombre_s'],
        'paterno'   => ['apellido_paterno', 'paterno', 'primer_apellido', 'ap_paterno', 'apellido_1'],
        'materno'   => ['apellido_materno', 'materno', 'segundo_apellido', 'ap_materno', 'apellido_2'],
        'carrera'   => ['nombre_carrera', 'nombre_de_la_carrera', 'carrera', 'programa', 'programa_educativo', 'especialidad'],
    ];

    /** Filas que se revisan al principio de cada hoja buscando el encabezado. */
    const FILAS_PARA_ENCABEZADO = 15;

    /** Alumnos dados de alta. */
    public $creados = 0;

    /** Alumnos que ya existían y se actualizaron. */
    public $actualizados = 0;

    /** De los actualizados, los que estaban dados de baja y se reactivaron. */
    public $reactivados = 0;

    /** Filas descartadas por venir sin matrícula. */
    public $ignorados = 0;

    /** Matrículas nuevas que no se dieron de alta por venir sin nombre. */
    public $sinNombre = [];

    /** Filas cuya matrícula ya venía antes en el archivo (se toma sólo la primera). */
    public $repetidas = 0;

    /** Matrículas ya procesadas en esta importación. */
    protected $vistas = [];

    /** Hojas del libro en las que sí se encontró el encabezado. */
    public $hojasLeidas = 0;

    /** Encabezados del archivo que no se usan (p. ej. CURP), para decirlo al terminar. */
    public $columnasIgnoradas = [];

    /** Alumnos ya registrados, por matrícula, para no consultar uno por uno. */
    protected $porMatricula;

    public function collection(Collection $rows)
    {
        $encabezado = $this->buscarEncabezado($rows);

        // Hojas de resumen o vacías: se saltan. Si no sirvió ninguna, quien llama
        // lo sabe por $hojasLeidas.
        if (! $encabezado) {
            return;
        }

        $this->hojasLeidas++;
        $this->cargarRegistrados();

        foreach ($rows as $numero => $row) {
            if ($numero <= $encabezado['fila']) {
                continue;
            }

            $this->procesarFila($row->values(), $encabezado['columnas']);
        }
    }

    /**
     * Da de alta o actualiza al alumno de una fila.
     */
    protected function procesarFila(Collection $fila, array $columnas): void
    {
        $dato = function ($campo) use ($fila, $columnas) {
            if (! isset($columnas[$campo])) {
                return null;   // la columna no viene en el archivo
            }

            $valor = preg_replace('/\s+/u', ' ', trim((string) ($fila[$columnas[$campo]] ?? '')));

            return $valor === '' ? null : $valor;
        };

        // La matrícula en mayúsculas y sin espacios: "c263110484 " es C263110484
        $matricula = $dato('matricula') !== null ? mb_strtoupper(str_replace(' ', '', $dato('matricula')), 'UTF-8') : null;

        if ($matricula === null) {
            $this->ignorados++;
            return;
        }

        // La misma matrícula dos veces en el archivo: se toma sólo la primera
        if (isset($this->vistas[$matricula])) {
            $this->repetidas++;
            return;
        }
        $this->vistas[$matricula] = true;

        // mb_strtoupper respeta los acentos: strtoupper dejaría "JOSé" en vez de "JOSÉ",
        // y ese nombre se imprime luego en listas de asistencia y reportes. Así
        // "Ingeniería En Gestión Empresarial" queda igual que las que ya existen.
        $mayusculas = fn($texto) => $texto === null ? null : mb_strtoupper($texto, 'UTF-8');

        $datos = array_filter([
            'name'             => $mayusculas($dato('nombre')),
            'apellido_paterno' => $mayusculas($dato('paterno')),
            'apellido_materno' => $mayusculas($dato('materno')),
            'carrera'          => $mayusculas($dato('carrera')),
        ], fn($valor) => $valor !== null);

        $user = $this->porMatricula[$matricula] ?? null;

        if ($user) {
            // Si estaba dado de baja y viene en la lista oficial de inscritos, vuelve
            if (! $user->activo) {
                $user->reactivar();
                $this->reactivados++;
            }

            // Sólo lo que trae el archivo; lo que falta se queda como estaba
            $user->fill($datos);
            if ($user->isDirty()) {
                $user->save();
            }

            $this->actualizados++;
            return;
        }

        // Un alumno nuevo necesita al menos su nombre
        if (empty($datos['name'])) {
            $this->sinNombre[] = $matricula;
            return;
        }

        User::create($datos + [
            'matricula' => $matricula,
            'username'  => $matricula,
            'email'     => 'L' . $matricula . '@' . config('marca.dominio_correo'),
            'password'  => Hash::make($matricula),
            'rol'       => 'Alumno',
        ]);

        $this->creados++;
    }

    /**
     * Busca en las primeras filas de la hoja una que sirva de encabezado: la que
     * tenga la matrícula y el nombre. Devuelve en qué fila está y en qué columna
     * quedó cada dato, o null si esta hoja no trae lo que hace falta.
     */
    protected function buscarEncabezado(Collection $rows): ?array
    {
        foreach ($rows as $numero => $row) {
            if ($numero >= self::FILAS_PARA_ENCABEZADO) {
                break;
            }

            $columnas = [];
            $sinUsar = [];

            foreach ($row->values() as $columna => $valor) {
                $titulo = Str::slug((string) $valor, '_');

                if ($titulo === '') {
                    continue;
                }

                $campo = null;
                foreach (self::COLUMNAS as $nombreCampo => $alias) {
                    if (! isset($columnas[$nombreCampo]) && in_array($titulo, $alias, true)) {
                        $campo = $nombreCampo;
                        break;
                    }
                }

                if ($campo) {
                    $columnas[$campo] = $columna;
                } else {
                    $sinUsar[] = trim((string) $valor);
                }
            }

            if (isset($columnas['matricula'], $columnas['nombre'])) {
                $this->columnasIgnoradas = array_values(array_unique(array_merge($this->columnasIgnoradas, $sinUsar)));

                return ['fila' => $numero, 'columnas' => $columnas];
            }
        }

        return null;
    }

    /** Los alumnos que ya existen, por matrícula (en mayúsculas para comparar). */
    protected function cargarRegistrados(): void
    {
        if ($this->porMatricula !== null) {
            return;
        }

        $this->porMatricula = User::where('rol', 'Alumno')
            ->whereNotNull('matricula')
            ->get()
            ->keyBy(fn($u) => mb_strtoupper(trim($u->matricula), 'UTF-8'))
            ->all();
    }
}
