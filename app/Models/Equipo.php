<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;

    protected $fillable = [
        'centro_computo_id',
        'numero_maquina',
        'estado',
        'usos_acumulados',
        'usos_historicos',
        'ultimo_mantenimiento' 
    ];

    // Relación: Un equipo pertenece a un laboratorio
    public function centroComputo()
    {
        return $this->belongsTo(CentroComputo::class);
    }
}
