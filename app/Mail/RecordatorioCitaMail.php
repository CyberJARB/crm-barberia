<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Cita $cita) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->cita->tenant->mailFromAddress(),
                $this->cita->tenant->mailFromName(),
            ),
            subject: 'Erinnerung: Ihr Termin morgen – '.$this->cita->tenant->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.recordatorio-cita');
    }
}
