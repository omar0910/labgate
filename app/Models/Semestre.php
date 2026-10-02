<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Semestre extends Model
{
    use HasFactory;

    // ESTA ES LA PARTE IMPORTANTE
    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'es_activo' // <--- Si falta este o las fechas, no se guardarán
    ];

    /**
     * Devuelve el semestre marcado como activo (o null si no hay ninguno).
     * Centraliza el patrón que se repetía en los controladores y en los Jobs de correo.
     */
    public static function activo(): ?self
    {
        return static::where('es_activo', 1)->first();
    }

    /**
     * Indica si una fecha cae dentro del periodo de clases de este semestre.
     * Mismo criterio que usa "EL GUARDIÁN DEL SEMESTRE" de los Jobs de correo.
     */
    public function contieneFecha($fecha): bool
    {
        $dia = Carbon::parse($fecha)->startOfDay();
        $inicio = Carbon::parse($this->fecha_inicio)->startOfDay();
        $fin = Carbon::parse($this->fecha_fin)->endOfDay();

        return !$dia->lessThan($inicio) && !$dia->greaterThan($fin);
    }
}
