<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteSemanalAlumno extends Mailable
{
    use Queueable, SerializesModels;

    public $alumno;
    public $resumen;
    public $desgloseMaterias; 

    /**
     * Create a new message instance.
     */
    public function __construct($alumno, $resumen, $desgloseMaterias)
    {
        $this->alumno = $alumno;
        $this->resumen = $resumen;
        $this->desgloseMaterias = $desgloseMaterias; // <-- Guardamos el dato
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📊 Tu Resumen Semanal de Asistencias - Centro de Cómputo',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte-semanal-alumno',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
