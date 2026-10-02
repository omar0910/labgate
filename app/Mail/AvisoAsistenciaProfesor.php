<?php

namespace App\Mail;

use App\Models\AsistenciaProfesor;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al profesor del estado que se le registró en una de sus clases.
 */
class AvisoAsistenciaProfesor extends Mailable
{
    use Queueable, SerializesModels;

    public $profesor;
    public $registro;

    public function __construct(User $profesor, AsistenciaProfesor $registro)
    {
        $this->profesor = $profesor;
        $this->registro = $registro;
    }

    public function envelope(): Envelope
    {
        $asunto = match ($this->registro->estado) {
            'asistio'     => 'Clase registrada como impartida',
            'falta'       => 'Falta registrada en una de tus clases',
            'justificado' => 'Ausencia justificada en una de tus clases',
            'retardo'     => 'Retardo registrado en una de tus clases',
            default       => 'Registro de clase actualizado',
        };

        return new Envelope(subject: $asunto . ' | ' . config('marca.sistema'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-asistencia-profesor');
    }
}
