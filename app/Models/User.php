<?php

namespace App\Models;

// ... (tus otros 'use' como HasFactory, Notifiable, etc.)
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Grupo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'rfc',
        'academia',
        'password',
        'rol',
        'matricula',
        'carrera',
        'username',
        'activo',
        'fecha_baja',
    ];

    /**
     * The attributes that should be hidden...
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast...
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
        'fecha_baja' => 'datetime',
    ];

    /**
     * Toda cuenta nueva nace activa también en memoria: la base ya pone 1 por
     * omisión, pero un usuario recién creado no lo trae hasta volver a leerlo, y
     * se tomaba por dado de baja (p. ej. al repetirse una matrícula en el Excel).
     */
    protected $attributes = [
        'activo' => true,
    ];

    /** Sólo las cuentas que no están dadas de baja. */
    public function scopeActivos($query)
    {
        return $query->where('users.activo', true);
    }

    /** Sólo las cuentas dadas de baja. */
    public function scopeDadosDeBaja($query)
    {
        return $query->where('users.activo', false);
    }

    /**
     * Da de baja la cuenta en lugar de borrarla.
     *
     * Borrar a un alumno borraba en cascada todo su historial de asistencias (y
     * a alguien del personal, sus reportes de fallas). Dado de baja no puede
     * entrar ni aparece en listas, grupos ni buscadores, pero su historial se
     * conserva en los reportes y se puede reactivar.
     */
    public function darDeBaja(): void
    {
        // Si se va con una sesión de uso libre abierta, se cierra: la computadora
        // no debe quedarse ocupada a su nombre.
        Asistencia::where('user_id', $this->id)
            ->where('tipo', 'Uso Libre')
            ->whereNull('fecha_hora_salida')
            ->update(['fecha_hora_salida' => now()]);

        $this->activo = false;
        $this->fecha_baja = now();
        $this->save();
    }

    /** Vuelve a activar una cuenta dada de baja. */
    public function reactivar(): void
    {
        $this->activo = true;
        $this->fecha_baja = null;
        $this->save();
    }

    /**
     * Los grupos (listas de clase) a los que pertenece este usuario (alumno).
     */
    public function grupos()
    {
        // 2. AÑADE ESTA FUNCIÓN COMPLETA
        // Esta es la relación "muchos a muchos" que conecta User con Grupo
        // a través de la tabla 'alumno_grupo'.
        // withTimestamps: la tabla tiene created_at, pero nunca se llenaba. Con la
        // fecha de inscripción, no se le cuentan al alumno las faltas de clases de
        // antes de que lo inscribieran (ver App\Support\FaltasSinRegistro).
        return $this->belongsToMany(Grupo::class, 'alumno_grupo', 'user_id', 'grupo_id')->withTimestamps();
    }

    // Esto permite usar: $user->nombre_completo
    public function getNombreCompletoAttribute()
    {
        // Si tiene apellidos separados, los une. Si no, usa el 'name' antiguo.
        //
        // Se filtran las partes vacías para que a quien no tenga apellido materno
        // no le quede un espacio colgando al final del nombre.
        if ($this->apellido_paterno) {
            $partes = array_filter([
                trim((string) $this->name),
                trim((string) $this->apellido_paterno),
                trim((string) $this->apellido_materno),
            ], function ($parte) {
                return $parte !== '';
            });

            return implode(' ', $partes);
        }

        return trim((string) $this->name);
    }

    /**
     * ¿A este usuario se le recuerda que cambie su contraseña?
     *
     * Sólo a alumnos y profesores: son los únicos que entran con una contraseña
     * que el sistema les puso al darlos de alta (su matrícula o su RFC) y que
     * cualquiera que la conozca podría adivinar. Las cuentas de administrador y
     * de encargado las crea el personal con la contraseña que elige, así que el
     * aviso no les corresponde.
     */
    public function recibeAvisoDeContrasena(): bool
    {
        return in_array($this->rol, ['Alumno', 'Profesor'], true);
    }

    /**
     * ¿Esta contraseña es la que el sistema le dio de arranque?
     *
     * Al dar de alta a la gente se usa un dato que ya se conoce: la matrícula en
     * los alumnos y el RFC en los profesores (el usuario coincide con uno de los
     * dos). Mientras alguien siga entrando con eso, cualquiera que sepa su
     * matrícula puede entrar en su nombre, así que se le recuerda cambiarla.
     *
     * Se compara sin distinguir mayúsculas ni espacios de sobra: el aviso debe
     * salir igual si el alta se hizo con la matrícula en minúsculas.
     */
    public function esContrasenaPorDefecto(?string $enClaro): bool
    {
        $enClaro = mb_strtolower(trim((string) $enClaro));

        if ($enClaro === '') {
            return false;
        }

        foreach ([$this->matricula, $this->rfc, $this->username] as $valorConocido) {
            if ($valorConocido && mb_strtolower(trim($valorConocido)) === $enClaro) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene las asistencias registradas por el usuario (alumno).
     */
    public function asistencias()
    {
        return $this->hasMany(\App\Models\Asistencia::class, 'user_id');
    }
}
