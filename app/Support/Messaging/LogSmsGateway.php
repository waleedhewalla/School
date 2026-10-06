<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Log;

/** Development driver: writes messages to the log instead of sending them. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $body): void
    {
        Log::info('SMS (not sent, log driver)', ['to' => $to, 'body' => $body]);
    }
}
