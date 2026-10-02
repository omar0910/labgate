<?php

namespace App\Demo;

use Carbon\Carbon;

/**
 * Lo que pasa en una clase de la demostración: quién llega, en qué computadora
 * se sienta y quién falta.
 *
 * Lo usan el sembrador (para el historial del semestre) y el simulador (para las
 * clases de hoy), así las dos cosas se comportan igual.
 */
class Sesion
{
    /**
     * Qué tan cumplido es un alumno, de 0 a 1. Sale de su id, para que sea el
     * mismo en el historial y en la simulación sin tener que guardarlo.
     */
    public static function constancia(int $alumnoId): float
    {
        // Uno de cada diez falta bastante más que el resto
        if (crc32('irregular' . $alumnoId) % 10 === 0) {
            return 0.55 + (crc32('i' . $alumnoId) % 200) / 1000;
        }

        return 0.82 + (crc32('c' . $alumnoId) % 160) / 1000;
    }

    /** Los que siempre trabajan con su propia laptop. */
    public static function usaLaptop(int $alumnoId): bool
    {
        return crc32('laptop' . $alumnoId) % 16 === 0;
    }

    /**
     * Las filas de asistencia de una clase en una fecha.
     *
     * @param  object  $horario   Con id, centro_computo_id, hora_inicio y hora_fin
     * @param  array   $miembros  Ids de los alumnos del grupo, ordenados: su posición es su lugar
     * @param  ?string $estadoProfesor  'asistio', o null si el profesor todavía no registra la clase
     * @param  bool    $enCurso   La clase no ha terminado: los registros quedan sin hora de salida
     * @param  array   $sinPc     Números de PC que no se pueden usar (en mantenimiento)
     * @param  array   $omitir    Alumnos que no se registran solos (el de demostración, mientras dura su clase)
     */
    public static function filas(object $horario, Carbon $fecha, array $miembros, int $capacidad, ?string $estadoProfesor,
        bool $enCurso = false, array $sinPc = [], array $omitir = []): array
    {
        $inicio = Carbon::parse($fecha->toDateString() . ' ' . $horario->hora_inicio);
        $fin = Carbon::parse($fecha->toDateString() . ' ' . $horario->hora_fin);
        $filas = [];

        foreach (array_values($miembros) as $posicion => $alumnoId) {
            if (in_array($alumnoId, $omitir, true)) {
                continue;
            }

            $base = [
                'horario_id' => $horario->id,
                'user_id' => $alumnoId,
                'fecha' => $fecha->toDateString(),
                'tipo' => 'Clase',
                'centro_computo_id' => $horario->centro_computo_id,
                'numero_maquina' => null,
                'equipo_personal' => 0,
                'comentario' => null,
                'fecha_hora_salida' => null,
            ];

            if (mt_rand(0, 9999) / 10000 < self::constancia($alumnoId)) {
                $pc = $posicion + 1;
                $conLaptop = self::usaLaptop($alumnoId) || $pc > $capacidad || in_array($pc, $sinPc, true);
                $registro = $inicio->copy()->addMinutes(mt_rand(-9, 11))->addSeconds(mt_rand(0, 59));

                $filas[] = array_merge($base, [
                    'estado' => 'presente',
                    'numero_maquina' => $conLaptop ? null : $pc,
                    'equipo_personal' => $conLaptop ? 1 : 0,
                    'fecha_hora_registro' => $registro->toDateTimeString(),
                    'fecha_hora_salida' => $enCurso ? null : $fin->toDateTimeString(),
                ]);

                continue;
            }

            // No llegó. Sólo queda anotado si el profesor pasó lista.
            if ($estadoProfesor !== 'asistio') {
                continue;
            }

            $azar = mt_rand(0, 99);
            if ($azar < 55) {
                $filas[] = array_merge($base, [
                    'estado' => 'falta',
                    'fecha_hora_registro' => $inicio->copy()->addMinutes(15)->toDateTimeString(),
                ]);
            } elseif ($azar < 68) {
                $filas[] = array_merge($base, [
                    'estado' => 'justificado',
                    'comentario' => Catalogo::JUSTIFICANTES[mt_rand(0, count(Catalogo::JUSTIFICANTES) - 1)],
                    'fecha_hora_registro' => $inicio->copy()->addMinutes(15)->toDateTimeString(),
                ]);
            }
            // El resto no tiene registro: el sistema lo cuenta como falta por su cuenta.
        }

        return $filas;
    }
}
