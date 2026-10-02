<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteSemanalProfesor extends Mailable
{
    use Queueable, SerializesModels;

    public $profesor;
    public $resumen;
    public $desgloseClases;

    public function __construct($profesor, $resumen, $desgloseClases)
    {
        $this->profesor = $profesor;
        $this->resumen = $resumen;
        $this->desgloseClases = $desgloseClases;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📋 Reporte Semanal de Cumplimiento en Laboratorios - ' . config('marca.sistema'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte-semanal-profesor',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
