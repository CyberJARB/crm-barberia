<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->tenant->mailFromAddress(), $this->tenant->mailFromName()),
            subject: '[Test] E-Mail-Konfiguration – '.$this->tenant->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test-email');
    }
}
