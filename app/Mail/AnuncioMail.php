<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Comunicado del operador a los dueños de los negocios (mantenimiento,
 * novedades…). B2B contractual — DSGVO Art. 6(1)(b).
 */
class AnuncioMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $asunto,
        public readonly string $mensaje,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('saas.producto').'] '.$this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.anuncio');
    }
}
