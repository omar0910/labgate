<?php

namespace App\Mail;

use App\Models\Asistencia;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirma al alumno cómo quedó registrada su asistencia en una clase.
 */
class AvisoAsistenciaClase extends Mailable
{
    use Queueable, SerializesModels;

    public $alumno;
    public $asistencia;

    /** Quién provocó el aviso: 'alumno', 'profesor' o 'ajuste'. */
    public $motivo;

    public function __construct(User $alumno, Asistencia $asistencia, string $motivo = 'alumno')
    {
        $this->alumno     = $alumno;
        $this->asistencia = $asistencia;
        $this->motivo     = $motivo;
    }

    public function envelope(): Envelope
    {
        $materia = optional(optional($this->asistencia->horario)->materia)->nombre_materia;

        $asunto = match ($this->asistencia->estado) {
            'falta'       => 'Falta registrada',
            'justificado' => 'Falta justificada',
            default       => 'Asistencia registrada',
        };

        if ($materia) {
            $asunto .= ' - ' . $materia;
        }

        return new Envelope(subject: $asunto . ' | ' . config('marca.sistema'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-asistencia-clase');
    }
}
