<?php

namespace App\Mail;

use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AttendanceAlert extends Mailable
{
    use Queueable;

    public function __construct(public string $schoolName, public string $body, public string $mailLocale)
    {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.attendance_subject', ['school' => $this->schoolName], $this->mailLocale));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendance-alert', with: ['dir' => Locale::direction($this->mailLocale)]);
    }
}
