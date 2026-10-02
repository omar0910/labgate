<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaInhabil extends Model
{
    protected $table = 'dias_inhabiles';
    protected $fillable = ['fecha', 'motivo', 'semestre_id'];

    public function semestre()
    {
        return $this->belongsTo(Semestre::class);
    }
}
