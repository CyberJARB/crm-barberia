<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// DSGVO Art.6(1)(b) — Transaktionale E-Mail zur Vertragserfüllung (Kontoerstellung)
class BienvenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen bei CRM Barberías – Ihr Konto ist bereit',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.bienvenida',
        );
    }
}
