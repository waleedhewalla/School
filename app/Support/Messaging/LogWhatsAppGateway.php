<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Log;

/** Development driver: writes WhatsApp messages to the log. */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function sendTemplate(string $to, string $template, string $language, array $parameters): void
    {
        Log::info('WhatsApp (not sent, log driver)', compact('to', 'template', 'language', 'parameters'));
    }
}
