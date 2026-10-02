<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CentroComputo;
use App\Models\Equipo;
use App\Models\Incidencia;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Asistencia extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'user_id',
        'horario_id',
        'numero_maquina',
        'equipo_personal',
        'estado',
        'comentario',
        'tipo',
        'fecha_hora_registro',
        'centro_computo_id',
        'fecha',
        'fecha_hora_salida',
    ];

    protected $casts = [
        'equipo_personal' => 'boolean',
    ];

    /**
     * Cómo se muestra el equipo del registro: "PC #5", "Equipo personal" o null si
     * no se anotó ninguno (p. ej. una lista capturada sin números de PC).
     */
    public function etiquetaEquipo(): ?string
    {
        if ($this->equipo_personal) {
            return 'Equipo personal';
        }

        return $this->numero_maquina ? 'PC #' . $this->numero_maquina : null;
    }

    /**
     * Deja solo los registros en los que la persona SÍ estuvo físicamente en el
     * centro: descarta faltas y justificados, y conserva los registros antiguos
     * que se guardaron sin estado.
     *
     * Es el criterio de las cifras de OCUPACIÓN (accesos al centro, uso por
     * laboratorio, hora pico y desgaste de equipos): quien falta no enciende una
     * máquina, y quien tiene falta justificada tampoco. Para el CUMPLIMIENTO
     * ACADÉMICO del alumno se usa otro criterio, donde el justificado sí cuenta
     * a su favor.
     */
    public function scopePresenciales($query)
    {
        return $query->where(function ($q) {
            $q->whereNotIn('estado', ['falta', 'justificado'])->orWhereNull('estado');
        });
    }

    /**
     * Define la relación: Una asistencia pertenece a un Usuario (Alumno).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define la relación: Una asistencia pertenece a un Horario.
     */
    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * Relación: Una asistencia pertenece a un Centro de Cómputo (opcionalmente).
     */
    public function centroComputo()
    {
        return $this->belongsTo(CentroComputo::class, 'centro_computo_id');
    }

    // --- FUNCIÓN PARA CONTROL DE CONCURRENCIA ---
    /**
     * ¿Alguien está usando esa PC ahora mismo?
     *
     * Uso libre: mientras no tenga hora de salida. Clase: sólo mientras la clase
     * no termina. Los registros de clase no llevan hora de salida hasta que
     * alguien abre el monitor, así que antes quien usó la PC en la clase de 8 a
     * 10 la dejaba "ocupada" todo el día y el de la clase siguiente no podía
     * registrarse en ella.
     */
    public static function estaOcupada($centro_id, $numero_maquina)
    {
        $ahora = now()->format('H:i:s');

        return self::where('centro_computo_id', $centro_id)
            ->where('numero_maquina', $numero_maquina)
            ->whereDate('fecha', \Carbon\Carbon::today())
            ->whereNull('fecha_hora_salida')
            ->where(function ($q) use ($ahora) {
                $q->where('tipo', '!=', 'Clase')
                    ->orWhereHas('horario', fn($h) => $h->whereTime('hora_fin', '>', $ahora));
            })
            ->exists();
    }

    // --- ANALÍTICA ---
    protected static function booted()
    {
        parent::booted();

        self::created(function ($asistencia) {
            $asistencia->sumarUsoAlEquipo();
        });

        // Al borrar un registro se devuelve el uso que se le sumó al crearlo. Antes no
        // se restaba, y un uso libre eliminado seguía contando en los reportes de
        // laboratorios y de desgaste de equipos.
        self::deleted(function ($asistencia) {
            if (! $asistencia->numero_maquina || ! $asistencia->centro_computo_id) {
                return;
            }

            $equipo = Equipo::where('centro_computo_id', $asistencia->centro_computo_id)
                ->where('numero_maquina', $asistencia->numero_maquina)
                ->first();

            if (! $equipo) {
                return;
            }

            if ($equipo->usos_historicos > 0) {
                $equipo->usos_historicos -= 1;
            }

            // El odómetro de desgaste se reinicia en cada mantenimiento: sólo se resta
            // si este uso se sumó DESPUÉS del último, porque si no ya se había borrado.
            $mantenimiento = $equipo->ultimo_mantenimiento ? \Carbon\Carbon::parse($equipo->ultimo_mantenimiento) : null;
            $contabaEnOdometro = ! $mantenimiento
                || ($asistencia->created_at && $asistencia->created_at->greaterThan($mantenimiento));

            if ($contabaEnOdometro && $equipo->usos_acumulados > 0) {
                $equipo->usos_acumulados -= 1;
            }

            $equipo->save();
        });
    }

    /**
     * Le suma un uso a la máquina del registro, si tiene una.
     *
     * Se hace al crear el registro y también cuando el alumno le pone su PC a una
     * asistencia que ya existía sin ella (la que le creó el pase de lista).
     */
    public function sumarUsoAlEquipo(): void
    {
        if (! $this->numero_maquina || ! $this->centro_computo_id) {
            return;
        }

        $equipo = Equipo::where('centro_computo_id', $this->centro_computo_id)
            ->where('numero_maquina', $this->numero_maquina)
            ->first();

        if ($equipo) {
            $equipo->usos_acumulados += 1;
            $equipo->usos_historicos += 1;
            $equipo->save();
        }
    }

    /**
     * Borra las asistencias de una consulta de una en una.
     *
     * Un borrado masivo (->delete() sobre la consulta) no avisa al modelo, así que
     * no devolvería los usos de las máquinas. Éste sí. Devuelve cuántas borró.
     */
    public static function borrarUnaPorUna($consulta): int
    {
        $borradas = 0;

        foreach ($consulta->get() as $asistencia) {
            $asistencia->delete();
            $borradas++;
        }

        return $borradas;
    }
}
