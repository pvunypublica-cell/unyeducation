<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LinkMagicoMail extends Mailable
{
    public function __construct(
        public readonly string $nomeParceiro,
        public readonly string $url,
        public readonly int $minutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu link de acesso — {$this->nomeParceiro}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.link-magico');
    }
}
