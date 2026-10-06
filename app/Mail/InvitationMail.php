<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    use Queueable;

    public function __construct(public Invitation $invitation, public string $url, public string $schoolName, public string $mailLocale)
    {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Invitation to join :school', ['school' => $this->schoolName], $this->mailLocale));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation', with: ['dir' => Locale::direction($this->mailLocale)]);
    }
}
