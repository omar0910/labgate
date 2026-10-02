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
 * Resumen de la sesión de uso libre que acaba de cerrarse.
 */
class AvisoUsoLibreSalida extends Mailable
{
    use Queueable, SerializesModels;

    public $alumno;
    public $sesion;

    public function __construct(User $alumno, Asistencia $sesion)
    {
        $this->alumno = $alumno;
        $this->sesion = $sesion;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sesión de Uso Libre terminada | ' . config('marca.sistema'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-uso-libre-salida');
    }
}
