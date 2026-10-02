<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incidencia extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'centro_computo_id',
        'numero_maquina',
        'categoria',
        'descripcion',
        'estado',
        // --- AGREGADOS PARA EL MANTENIMIENTO ---
        'solucion',
        'fecha_resolucion',
        'nota_resolucion',
        'resuelta_por',
    ];

    protected $casts = [
        'fecha_resolucion' => 'datetime',
    ];

    /**
     * Los estados que cuentan como resuelta. Versiones viejas del sistema
     * guardaban "resuelto": esos reportes no salían ni en pendientes ni en el
     * historial.
     */
    const RESUELTAS = ['resuelta', 'resuelto'];

    /** Quién cerró el reporte (vacío en los que se cerraron antes de guardarlo). */
    public function resolvio()
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /** Cuándo se cerró: la fecha guardada al cerrarlo, o su última modificación. */
    public function getFechaDeCierreAttribute()
    {
        return $this->fecha_resolucion ?? $this->updated_at;
    }

    /**
     * Relación con el Usuario que reportó.
     * CAMBIO IMPORTANTE: La renombramos de 'usuario' a 'user' 
     * para que coincida con el código estándar de Laravel y tu controlador.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con el Laboratorio
    public function centroComputo()
    {
        return $this->belongsTo(CentroComputo::class, 'centro_computo_id');
    }

    public function equipo()
    {
        // Relacionamos la incidencia con la tabla equipos, basándonos en el numero_maquina y el centro_id
        return $this->belongsTo(Equipo::class, 'numero_maquina', 'numero_maquina')
            ->where('centro_computo_id', $this->centro_computo_id);
    }
}
