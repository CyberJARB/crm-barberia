<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumenMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->tenant->mailFromAddress(),
                $this->tenant->mailFromName(),
            ),
            subject: '📊 Ihr Update '.now()->format('d.m.Y, H:i').' Uhr – '.$this->tenant->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.resumen');
    }
}
