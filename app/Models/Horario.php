<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Horario extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'materia_id',
        'grupo_id',
        'centro_computo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'tipo_reserva',
        'fecha_especial',
        'impartida',
        'comentario',
        'semestre_id',
    ];

    // Relación con el Profesor
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación con la Materia
    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    // Relación con el Grupo (Lista de Alumnos)
    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    // Relación con el Centro de Cómputo
    public function centroComputo()
    {
        return $this->belongsTo(CentroComputo::class);
    }

    // Y agrega la relación para que funcione el filtro:
    public function semestre()
    {
        return $this->belongsTo(Semestre::class);
    }

    // Relación: Un horario tiene registros de asistencia DEL PROFESOR
    public function asistenciasProfesor()
    {
        return $this->hasMany(AsistenciaProfesor::class, 'horario_id');
    }

    // Relación: las asistencias de los ALUMNOS a esta clase
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'horario_id');
    }

    /**
     * ¿Se puede eliminar para siempre sin perder historial?
     *
     * Sólo si nadie registró asistencia en esta clase. La base borraría en
     * cascada las asistencias de los profesores, y las de los alumnos ni siquiera
     * dejaría borrarlas; en cualquier caso, ese historial es lo que alimenta los
     * reportes y no debe perderse por limpiar la papelera.
     */
    public function sePuedeEliminarDefinitivamente(): bool
    {
        return ! $this->asistencias()->exists() && ! $this->asistenciasProfesor()->exists();
    }

    /**
     * Qué asignatura es este horario: misma materia, mismo grupo, mismo docente, y
     * si es clase fija o reserva especial. Una materia que se da lunes y miércoles
     * son dos horarios (uno por sesión), pero es una sola asignatura.
     */
    public function claveDeAsignatura(): string
    {
        return $this->materia_id . '|' . $this->grupo_id . '|' . $this->user_id
            . '|' . ($this->tipo_reserva === 'especial' ? 'especial' : 'fija');
    }

    /**
     * Las sesiones de la misma asignatura en el mismo semestre, incluida ésta.
     */
    public function scopeDeLaMismaAsignatura($query, Horario $horario)
    {
        $query->where('materia_id', $horario->materia_id)
            ->where('grupo_id', $horario->grupo_id)
            ->where('user_id', $horario->user_id)
            ->where('semestre_id', $horario->semestre_id);

        if ($horario->tipo_reserva === 'especial') {
            return $query->where('tipo_reserva', 'especial');
        }

        return $query->where(function ($q) {
            $q->where('tipo_reserva', '!=', 'especial')->orWhereNull('tipo_reserva');
        });
    }

    /**
     * Las clases que tocan en una fecha: las fijas de ese día de la semana y las
     * reservas especiales de ESA fecha. Antes se buscaba sólo por el día de la
     * semana, y una reserva especial de un miércoles salía todos los miércoles.
     */
    public function scopeQueTocanEl($query, $fecha)
    {
        $fecha = \Carbon\Carbon::parse($fecha);
        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $dia = $dias[$fecha->dayOfWeekIso];

        return $query->where(function ($q) use ($dia, $fecha) {
            $q->where(function ($fijas) use ($dia) {
                $fijas->where('dia_semana', $dia)
                    ->where(function ($t) {
                        $t->where('tipo_reserva', '!=', 'especial')->orWhereNull('tipo_reserva');
                    });
            })->orWhere(function ($especiales) use ($fecha) {
                $especiales->where('tipo_reserva', 'especial')
                    ->whereDate('fecha_especial', $fecha->format('Y-m-d'));
            });
        });
    }

    /**
     * Por qué no se puede registrar asistencia de esta clase en esa fecha, o null
     * si sí se puede: que ya haya llegado (salvo que se admita una futura, con
     * $siEsFutura = null), que esté dentro de su semestre y que la clase se dé ese
     * día (su día de la semana, o la fecha de la reserva especial).
     *
     * Lo usan el pase de lista del profesor, la Bitácora y el registro del
     * profesor en "Gestión de Clases" / "Clases de hoy". Antes se podía guardar la
     * lista de un martes con fecha de miércoles, y esa "clase" contaba como dada.
     *
     * La Bitácora pasa $exigirSuDia = false: ahí se capturan a propósito las
     * reposiciones (una clase dada en un día que no es el suyo).
     */
    public function problemaParaLaFecha(string $fecha, ?string $siEsFutura = 'No se puede pasar lista de una fecha que aún no llega.', bool $exigirSuDia = true): ?string
    {
        $dia = \Carbon\Carbon::parse($fecha);

        if ($siEsFutura !== null && $fecha > \Carbon\Carbon::today()->toDateString()) {
            return $siEsFutura;
        }

        if ($this->semestre && ! $this->semestre->contieneFecha($dia)) {
            return 'El ' . $dia->translatedFormat('d \d\e F \d\e Y') . ' está fuera del semestre de esta clase.';
        }

        if ($exigirSuDia && ! static::whereKey($this->id)->queTocanEl($dia)->exists()) {
            return 'Esta clase no se imparte el ' . $dia->translatedFormat('l d \d\e F') . '.';
        }

        return null;
    }

    /**
     * Día de la semana de la clase, del 1 (lunes) al 7 (domingo). Ignora acentos
     * y mayúsculas, por si algún registro quedó como "Miercoles".
     */
    public function numeroDeDia(): ?int
    {
        $dias = ['lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7];

        return $dias[strtolower(\Illuminate\Support\Str::ascii((string) $this->dia_semana))] ?? null;
    }

    /**
     * Qué hora anotar en un registro NUEVO al capturar la lista de esta clase en
     * una fecha: la hora real si se captura mientras dura la clase; si no (una
     * lista pasada, o la de hoy capturada ya terminada), el día de la clase a su
     * hora de inicio. Así la "hora pico" de los reportes no sale a la hora en que
     * se pasaron las hojas.
     */
    public function horaDeRegistroDeLista($fecha): \Carbon\Carbon
    {
        $dia = \Carbon\Carbon::parse($fecha)->format('Y-m-d');

        if (! $this->hora_inicio) {
            return $dia === now()->format('Y-m-d') ? now() : \Carbon\Carbon::parse($dia . ' 00:00:00');
        }

        $inicio = \Carbon\Carbon::parse($dia . ' ' . \Carbon\Carbon::parse($this->hora_inicio)->format('H:i:s'));
        $fin = $this->hora_fin
            ? \Carbon\Carbon::parse($dia . ' ' . \Carbon\Carbon::parse($this->hora_fin)->format('H:i:s'))
            : $inicio->copy()->addHour();

        return now()->between($inicio, $fin) ? now() : $inicio;
    }

    /** Para ordenar las sesiones de una asignatura: por fecha (reservas), día y hora. */
    public function ordenDeSesion(): string
    {
        return ($this->fecha_especial ?? '') . '|' . ($this->numeroDeDia() ?? 9) . '|' . $this->hora_inicio;
    }

    /** "Lunes de 14:00 a 16:00", o la fecha si es una reserva especial. */
    public function etiquetaDeSesion(): string
    {
        $cuando = $this->tipo_reserva === 'especial' && $this->fecha_especial
            ? \Carbon\Carbon::parse($this->fecha_especial)->format('d/m/Y')
            : $this->dia_semana;

        return $cuando . ' de '
            . ($this->hora_inicio ? \Carbon\Carbon::parse($this->hora_inicio)->format('H:i') : '--') . ' a '
            . ($this->hora_fin ? \Carbon\Carbon::parse($this->hora_fin)->format('H:i') : '--');
    }
}
