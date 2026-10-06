<?php

namespace App\Mail;

use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A short message from the school to a family, with its own subject line. */
class SchoolNotice extends Mailable
{
    use Queueable;

    public function __construct(public string $schoolName, public string $noticeSubject, public string $body, public string $mailLocale)
    {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->schoolName.': '.$this->noticeSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendance-alert', with: ['dir' => Locale::direction($this->mailLocale)]);
    }
}
