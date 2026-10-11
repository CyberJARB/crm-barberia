<?php

namespace App\Mail;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GeburtstagMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Cliente $cliente) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->cliente->tenant->mailFromAddress(),
                $this->cliente->tenant->mailFromName(),
            ),
            subject: 'Alles Gute zum Geburtstag! 🎉 – '.$this->cliente->tenant->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.geburtstag');
    }
}
