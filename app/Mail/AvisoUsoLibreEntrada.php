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
 * Avisa al alumno de que se le abrió una sesión de uso libre, y le recuerda
 * que tiene que cerrarla al terminar.
 */
class AvisoUsoLibreEntrada extends Mailable
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
        return new Envelope(
            subject: 'Entrada registrada en Uso Libre - PC #' . $this->sesion->numero_maquina . ' | ' . config('marca.sistema')
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-uso-libre-entrada');
    }
}
