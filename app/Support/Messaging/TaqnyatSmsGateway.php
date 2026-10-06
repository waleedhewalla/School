<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Http;

/** Taqnyat SMS API (Saudi provider). Check field names against Taqnyat's current docs before going live. */
class TaqnyatSmsGateway implements SmsGateway
{
    public function __construct(private string $token, private string $sender, private string $url) {}

    public function send(string $to, string $body): void
    {
        $response = Http::withToken($this->token)->acceptJson()->timeout(15)->post($this->url, [
            'recipients' => [$to],
            'body' => $body,
            'sender' => $this->sender,
        ]);

        if ($response->failed()) {
            throw new SmsFailed('Taqnyat: '.($response->json('message') ?? $response->status()));
        }
    }
}
