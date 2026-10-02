<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lo que el sistema ya aprendió del archivo de horarios del plantel.
 *
 * Cada renglón dice a qué corresponde un texto del archivo: que el área "CI" es
 * el laboratorio CAD 2, que "EMEZA" es tal profesor o que "INTROD. A
 * PROGRAMACIÓN" es la materia "Introducción a la Programación". Se guarda al
 * confirmar una importación, para no volver a preguntarlo nunca.
 */
class EquivalenciaImportacion extends Model
{
    protected $table = 'equivalencias_importacion';

    protected $fillable = ['tipo', 'clave', 'texto', 'destino_id'];

    /** Devuelve [clave normalizada => destino_id] de un tipo. */
    public static function mapa(string $tipo): array
    {
        return static::where('tipo', $tipo)->pluck('destino_id', 'clave')->all();
    }

    /** Aprende (o corrige) a qué corresponde un texto del archivo. */
    public static function aprender(string $tipo, string $clave, string $texto, int $destinoId): void
    {
        static::updateOrCreate(
            ['tipo' => $tipo, 'clave' => $clave],
            ['texto' => $texto, 'destino_id' => $destinoId]
        );
    }
}
