<?php

namespace App\Support;

/**
 * Compara los textos del archivo del plantel con los nombres del sistema.
 *
 * El archivo no escribe las cosas como el sistema: las materias vienen
 * abreviadas ("INTROD. A PROGRAMACIÓN" por "Introducción a la Programación") y
 * los docentes vienen en clave ("EMEZA" por Juan Eduardo Meza Bocanegra). Aquí
 * está la parte que propone a qué corresponde cada cosa; la última palabra
 * siempre la tiene la persona que confirma la importación.
 */
class Emparejador
{
    /** Palabras que no ayudan a distinguir un nombre de materia de otro. */
    const VACIAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'A', 'AL', 'EN', 'PARA', 'CON', 'POR'];

    /** Partículas que van pegadas al apellido en las claves de docente. */
    const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA', 'Y'];

    /** Mayúsculas, sin acentos y sin puntuación: así se comparan los textos. */
    public static function normalizar(?string $texto): string
    {
        $t = mb_strtoupper(trim((string) $texto), 'UTF-8');

        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ü' => 'U', 'Ñ' => 'N', 'À' => 'A', 'È' => 'E', 'Ì' => 'I',
            'Ò' => 'O', 'Ù' => 'U',
        ]);

        $t = preg_replace('/[^A-Z0-9 ]+/', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /** Las palabras que sí distinguen, ya normalizadas. */
    public static function palabras(string $texto): array
    {
        $palabras = array_filter(
            explode(' ', self::normalizar($texto)),
            function ($palabra) {
                return $palabra !== '' && ! in_array($palabra, self::VACIAS, true);
            }
        );

        return array_values($palabras);
    }

    /**
     * Qué tanto se parecen dos nombres, de 0 a 1, aceptando abreviaturas.
     *
     * Cada palabra del primero busca una del segundo de la que sea principio (o
     * al revés): así "INTROD" casa con "INTRODUCCION" y "PROG" con "PROGRAMACION".
     */
    public static function parecido(string $uno, string $otro): float
    {
        $a = self::palabras($uno);
        $b = self::palabras($otro);

        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $disponibles = $b;
        $aciertos = 0;

        foreach ($a as $palabra) {
            foreach ($disponibles as $i => $candidata) {
                if (self::mismaPalabra($palabra, $candidata)) {
                    $aciertos++;
                    unset($disponibles[$i]);
                    break;
                }
            }
        }

        return $aciertos / max(count($a), count($b));
    }

    /** ¿Son la misma palabra, aunque una venga abreviada o cambie de terminación? */
    protected static function mismaPalabra(string $uno, string $otro): bool
    {
        if ($uno === $otro) {
            return true;
        }

        // "EJECUTIVA" y "EJECUTIVO", "MAQUINAS" y "MAQUINA": el archivo y el
        // catálogo no siempre coinciden en el género o el número.
        $uno = self::raiz($uno);
        $otro = self::raiz($otro);

        if ($uno === $otro) {
            return true;
        }

        // "INTROD" es principio de "INTRODUCCION". Se piden al menos cuatro
        // letras para que "EL" no case con "ELECTRONICA".
        $corta = strlen($uno) <= strlen($otro) ? $uno : $otro;
        $larga = $corta === $uno ? $otro : $uno;

        return strlen($corta) >= 4 && str_starts_with($larga, $corta);
    }

    /** La palabra sin su terminación de género o número: EJECUTIVA -> EJECUTIV. */
    protected static function raiz(string $palabra): string
    {
        if (strlen($palabra) < 5) {
            return $palabra;
        }

        foreach (['ES', 'AS', 'OS', 'A', 'O', 'E', 'S'] as $terminacion) {
            if (str_ends_with($palabra, $terminacion)) {
                return substr($palabra, 0, -strlen($terminacion));
            }
        }

        return $palabra;
    }

    /**
     * De una lista [id => nombre], la mejor coincidencia para $texto.
     *
     * Devuelve ['id' => ..., 'puntaje' => 0..1, 'seguro' => bool]. Se considera
     * segura cuando el parecido es muy alto y ninguna otra opción se le acerca:
     * si hay dudas, es mejor preguntar que adivinar.
     */
    public static function mejorCoincidencia(string $texto, array $candidatos): ?array
    {
        $puntajes = [];

        foreach ($candidatos as $id => $nombre) {
            $puntajes[$id] = self::parecido($texto, (string) $nombre);
        }

        if (empty($puntajes)) {
            return null;
        }

        arsort($puntajes);
        $ids = array_keys($puntajes);
        $mejor = $ids[0];
        $puntaje = $puntajes[$mejor];
        $segundo = count($ids) > 1 ? $puntajes[$ids[1]] : 0.0;

        if ($puntaje < 0.5) {
            return null;
        }

        // El catálogo a veces tiene la misma materia dos veces con claves
        // distintas (ACC0906 y ACC-0906). Si los primeros lugares se llaman
        // exactamente igual, no es duda sobre qué materia es: es un duplicado.
        $empatados = array_keys(array_filter($puntajes, function ($p) use ($puntaje) {
            return $p === $puntaje;
        }));
        $nombresEmpatados = array_unique(array_map(function ($id) use ($candidatos) {
            return self::normalizar((string) $candidatos[$id]);
        }, $empatados));

        return [
            'id'        => $mejor,
            'puntaje'   => $puntaje,
            'seguro'    => $puntaje >= 0.85 && ($puntaje - $segundo) >= 0.15,
            'duplicado' => $puntaje >= 0.85 && count($empatados) > 1 && count($nombresEmpatados) === 1,
        ];
    }

    /**
     * Las claves con las que el archivo podría nombrar a esta persona.
     *
     * "JUAN EDUARDO MEZA BOCANEGRA" puede venir como JMEZA o EMEZA, y
     * "EDGAR DE LA ROSA AGUILAR" como EDELAROSA o EAGUILAR: la inicial es la de
     * cualquiera de sus nombres y el apellido va pegado, con sus partículas.
     */
    public static function clavesDeDocente(?string $nombres, ?string $paterno, ?string $materno): array
    {
        // De los nombres sólo se toma la inicial; las partículas no cuentan
        // ("MARIA DE LOS ANGELES" no aporta una "D").
        $nombres = array_values(array_filter(
            explode(' ', self::normalizar((string) $nombres)),
            function ($p) {
                return $p !== '' && ! in_array($p, self::PARTICULAS, true);
            }
        ));

        $apellidos = [];

        foreach ([$paterno, $materno] as $apellido) {
            // Aquí NO se quitan las partículas: son parte del apellido.
            $partes = array_values(array_filter(explode(' ', self::normalizar((string) $apellido))));

            if (! empty($partes)) {
                $apellidos[] = implode('', $partes);   // "DE LA ROSA" -> "DELAROSA"

                // También sin partículas, por si el archivo las omite: "ROSA".
                $sinParticulas = array_values(array_filter($partes, function ($p) {
                    return ! in_array($p, self::PARTICULAS, true);
                }));
                if (! empty($sinParticulas)) {
                    $apellidos[] = implode('', $sinParticulas);
                }
            }
        }

        $claves = [];

        foreach ($apellidos as $apellido) {
            $claves[] = $apellido;
            foreach ($nombres as $nombre) {
                $claves[] = substr($nombre, 0, 1) . $apellido;
            }
        }

        return array_values(array_unique($claves));
    }
}
