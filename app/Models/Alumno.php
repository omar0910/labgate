<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alumno extends Model
{
    use HasFactory;
    protected $fillable = ['matricula', 'nombre_completo'];

    /**
     * Los grupos a los que pertenece este alumno.
     */
    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'alumno_grupo');
    }
}