<?php

namespace App\Support;

/**
 * Búsqueda por palabras sueltas para los listados del sistema.
 *
 * El problema que resuelve: los nombres viven repartidos en varias columnas
 * (name, apellido_paterno, apellido_materno), así que un LIKE con la frase
 * completa nunca encuentra nada. Escribir "ROCIO AMADOR" no daba resultados
 * porque ninguna columna contiene esa frente entera.
 *
 * Aquí el texto se parte en palabras y se exige que **todas** aparezcan en
 * alguna de las columnas indicadas. Así funcionan por igual "ROCIO AMADOR",
 * "AMADOR ROCIO" y el nombre completo, sin importar el orden.
 *
 * Los acentos y las mayúsculas no hacen falta tratarlos: la base usa una
 * intercalación (utf8mb4_..._ai_ci) que ya los ignora al comparar.
 */
class Busqueda
{
    /** A partir de esta longitud se tolera un error de dedo al final de la palabra. */
    const LARGO_MINIMO_TOLERANCIA = 5;

    /**
     * Parte lo que escribió el usuario en palabras buscables.
     *
     * Recorta los espacios sobrantes, que es un fallo muy fácil de provocar
     * al copiar y pegar desde una lista o un correo.
     */
    public static function palabras($texto): array
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return [];
        }

        $palabras = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);

        return $palabras ?: [];
    }

    /**
     * Añade a la consulta la condición de búsqueda.
     *
     * Las columnas pueden ser propias ('name') o de una relación
     * ('materia.nombre_materia'), y se mezclan sin problema.
     *
     * Con $tolerante activado se recorta la última letra de cada palabra larga,
     * de modo que "AMADOX" siga encontrando a "AMADOR". Sólo se usa como segundo
     * intento, cuando la búsqueda normal no devolvió nada, para no ensuciar los
     * resultados buenos con parecidos.
     */
    public static function aplicar($query, $texto, array $columnas, bool $tolerante = false)
    {
        $palabras = self::palabras($texto);

        if (empty($palabras) || empty($columnas)) {
            return $query;
        }

        foreach ($palabras as $palabra) {
            $termino = $tolerante ? self::recortar($palabra) : $palabra;

            // Cada palabra debe aparecer en alguna columna (Y entre palabras, O entre columnas).
            $query->where(function ($q) use ($termino, $columnas) {
                foreach ($columnas as $columna) {
                    self::condicion($q, $columna, $termino);
                }
            });
        }

        return $query;
    }

    /**
     * Ejecuta la búsqueda y la reintenta con tolerancia si no encontró nada.
     *
     * Recibe una función que arma la consulta base (con sus filtros y su orden),
     * porque para el segundo intento hay que construirla otra vez desde cero.
     */
    public static function paginar(callable $consultaBase, $texto, array $columnas, int $porPagina = 15)
    {
        $resultados = self::aplicar($consultaBase(), $texto, $columnas)
            ->paginate($porPagina)
            ->withQueryString();

        // Segundo intento, más permisivo, sólo si el primero se fue en blanco.
        if ($resultados->isEmpty() && self::palabras($texto)) {
            $resultados = self::aplicar($consultaBase(), $texto, $columnas, true)
                ->paginate($porPagina)
                ->withQueryString();
        }

        return $resultados;
    }

    /**
     * Igual que paginar(), pero trae todos los resultados. Para los listados que
     * agrupan los registros antes de paginarlos.
     */
    public static function todos(callable $consultaBase, $texto, array $columnas)
    {
        $resultados = self::aplicar($consultaBase(), $texto, $columnas)->get();

        if ($resultados->isEmpty() && self::palabras($texto)) {
            $resultados = self::aplicar($consultaBase(), $texto, $columnas, true)->get();
        }

        return $resultados;
    }

    /**
     * Agrega una condición OR para una columna, propia o de una relación.
     */
    protected static function condicion($q, string $columna, string $termino): void
    {
        if (str_contains($columna, '.')) {
            [$relacion, $campo] = explode('.', $columna, 2);

            $q->orWhereHas($relacion, function ($sub) use ($campo, $termino) {
                $sub->where($campo, 'LIKE', '%' . $termino . '%');
            });

            return;
        }

        $q->orWhere($columna, 'LIKE', '%' . $termino . '%');
    }

    /**
     * Quita la última letra de las palabras largas para absorber erratas.
     */
    protected static function recortar(string $palabra): string
    {
        if (mb_strlen($palabra) < self::LARGO_MINIMO_TOLERANCIA) {
            return $palabra;
        }

        return mb_substr($palabra, 0, mb_strlen($palabra) - 1);
    }
}
