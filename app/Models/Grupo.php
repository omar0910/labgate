<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Grupo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_grupo',
        'semestre_id',
    ];

    public function semestre()
    {
        return $this->belongsTo(Semestre::class);
    }

    /**
     * La clave del grupo sin el nombre de la materia: de "3KM-COSTOS EMPRESARIALES",
     * "3KM"; de "1GV(a)-FÍSICA EN GASTRONOMÍA", "1GV(a)".
     *
     * Para donde la materia ya va escrita al lado y repetirla sólo ocupa espacio
     * (el PDF de horarios). No cambia el nombre del grupo: en los formularios y en
     * el resto del sistema se sigue viendo completo. Si el nombre no trae ese
     * formato, se devuelve tal cual.
     */
    public function getClaveCortaAttribute(): string
    {
        $nombre = trim((string) $this->nombre_grupo);

        if (! str_contains($nombre, '-')) {
            return $nombre;
        }

        $clave = trim(explode('-', $nombre, 2)[0]);

        // Una clave es corta y sin espacios, y lleva el número del semestre ("3KM",
        // "1GV(a)") o son unas pocas mayúsculas ("ESV"). "Fundamentos-1SM" o
        // "Grupo A - tarde" no lo son y se dejan completos.
        $esClave = mb_strlen($clave) <= 10
            && preg_match('/\s/u', $clave) === 0
            && (preg_match('/\d/', $clave) === 1 || preg_match('/^[A-ZÑ]{2,5}$/u', $clave) === 1);

        return $esClave ? $clave : $nombre;
    }

    /**
     * Los alumnos que pertenecen a este grupo.
     * ESTA ES LA CORRECTA, USA LA TABLA 'alumno_grupo'
     */
    public function alumnos()
    {
        // Sin los dados de baja: dejan de salir en las listas de clase, pase de
        // lista y reportes del grupo. La inscripción se conserva por si se reactivan.
        // withTimestamps: guarda cuándo se inscribió (la tabla ya tenía las columnas,
        // pero quedaban vacías). Sirve para no contarle al alumno faltas de clases de
        // antes de su inscripción (ver App\Support\FaltasSinRegistro).
        return $this->belongsToMany(User::class, 'alumno_grupo', 'grupo_id', 'user_id')
            ->withTimestamps()
            ->where('users.activo', true);
    }
}
