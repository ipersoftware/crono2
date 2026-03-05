<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatePrenotazioneMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $oggettoRendered,
        public readonly string $corpoRendered,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoRendered);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template_prenotazione');
    }
}
