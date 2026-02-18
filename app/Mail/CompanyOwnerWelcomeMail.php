<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyOwnerWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ownerName,
        public string $companyName,
        public string $email,
        public string $plainPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NFTPayroll Hosgeldiniz',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company-owner-welcome',
            with: [
                'ownerName' => $this->ownerName,
                'companyName' => $this->companyName,
                'email' => $this->email,
                'plainPassword' => $this->plainPassword,
            ],
        );
    }
}
