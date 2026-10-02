<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentroComputo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_centro',
        'capacidad',
        'permite_uso_libre',
        'uso_libre_solo_en_sus_pcs',
    ];

    protected $casts = [
        'permite_uso_libre' => 'boolean',
        // El uso libre sólo se registra desde sus computadoras (no con laptop)
        'uso_libre_solo_en_sus_pcs' => 'boolean',
    ];

    // 1. Relación: Un Centro tiene muchos Equipos
    public function equipos()
    {
        return $this->hasMany(Equipo::class);
    }

    // 2. LA MAGIA AUTOMÁTICA
    protected static function booted()
    {
        // Cuando se CREA un nuevo laboratorio en tu panel
        static::created(function ($centro) {
            for ($i = 1; $i <= $centro->capacidad; $i++) {
                Equipo::create([
                    'centro_computo_id' => $centro->id,
                    'numero_maquina' => $i,
                    'estado' => 'disponible',
                    'usos_acumulados' => 0
                ]);
            }
        });

        // Cuando se ACTUALIZA un laboratorio (ej. aumentas la capacidad de 25 a 30)
        static::updated(function ($centro) {
            if ($centro->wasChanged('capacidad')) {
                // Se asegura que existan TODAS las PCs del 1 a la capacidad. Antes se
                // contaban las que había y se creaban a partir de ese número: si
                // faltaba una de en medio (la #3, por ejemplo), nunca se volvía a crear.
                $existentes = $centro->equipos()->pluck('numero_maquina')->flip();

                for ($i = 1; $i <= $centro->capacidad; $i++) {
                    if (! isset($existentes[$i])) {
                        Equipo::create([
                            'centro_computo_id' => $centro->id,
                            'numero_maquina' => $i,
                            'estado' => 'disponible',
                            'usos_acumulados' => 0,
                        ]);
                    }
                }
                // Nota: Si la capacidad disminuye, es mejor no borrar las PCs automáticamente 
                // para no perder el historial de mantenimiento. Solo las dejas ahí.
            }
        });
    }
}
