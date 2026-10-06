<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Http;

/** Unifonic REST SMS API (Saudi provider). Check field names against Unifonic's current docs before going live. */
class UnifonicSmsGateway implements SmsGateway
{
    public function __construct(private string $appSid, private string $senderId, private string $url) {}

    public function send(string $to, string $body): void
    {
        $response = Http::asForm()->timeout(15)->post($this->url, [
            'AppSid' => $this->appSid,
            'SenderID' => $this->senderId,
            'Recipient' => $to,
            'Body' => $body,
        ]);

        if ($response->failed() || $response->json('success') === false) {
            throw new SmsFailed('Unifonic: '.($response->json('message') ?? $response->status()));
        }
    }
}
