<?php

namespace App\Mail;

use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdmissionUpdate extends Mailable
{
    use Queueable;

    public function __construct(public string $schoolName, public string $reference, public string $body, public string $mailLocale)
    {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('admissions.subject', ['school' => $this->schoolName, 'reference' => $this->reference], $this->mailLocale));
    }

    public function content(): Content
    {
        // Same plain layout as attendance alerts.
        return new Content(view: 'mail.attendance-alert', with: ['dir' => Locale::direction($this->mailLocale)]);
    }
}
