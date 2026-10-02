<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Asistencia; // <--- Importamos el modelo

class AsistenciaRegistrada extends Mailable
{
    use Queueable, SerializesModels;

    public $asistencia; // Variable pública para usarla en la vista

    // Recibimos la asistencia al crear el correo
    public function __construct(Asistencia $asistencia)
    {
        $this->asistencia = $asistencia;
    }

    // Definimos el asunto del correo
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de Asistencia - Centro de Cómputo',
        );
    }

    // Definimos qué vista (HTML) se va a enviar
    public function content(): Content
    {
        return new Content(
            view: 'emails.asistencia_registrada',
        );
    }
}
