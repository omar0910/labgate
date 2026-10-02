<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsistenciaProfesor extends Model
{
    use HasFactory;

    // Indicamos la tabla si no sigue la convención (opcional, pero buena práctica)
    protected $table = 'asistencia_profesores';

    protected $fillable = ['horario_id', 'fecha', 'estado', 'observaciones'];

    // Esta es la relación que le faltaba a Laravel
    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * Los profesores con más faltas registradas en el semestre, de mayor a menor.
     * Lo usan el inicio del administrador y el del encargado.
     *
     * Sólo cuentan las clases que siguen existiendo: si la clase está en la
     * papelera (borrada a mano o retirada por el importador de horarios), su
     * horario llega vacío y no hay a quién atribuirle la falta.
     */
    public static function rankingDeFaltas(?Semestre $semestre, int $cuantos = 5)
    {
        if (! $semestre) {
            return collect();
        }

        return self::with('horario.user')
            ->where('estado', 'falta')
            ->whereBetween('fecha', [$semestre->fecha_inicio, $semestre->fecha_fin])
            ->whereHas('horario')
            ->get()
            ->groupBy(fn($falta) => $falta->horario->user_id)
            ->map(fn($grupoFaltas) => (object) [
                'user' => $grupoFaltas->first()->horario->user,
                'total_faltas' => $grupoFaltas->count(),
            ])
            ->sortByDesc('total_faltas')
            ->take($cuantos)
            ->values();
    }
}
