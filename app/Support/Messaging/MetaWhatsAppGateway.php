<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (Meta). The template must be created and approved in
 * WhatsApp Manager first, with the same name, language and number of
 * body parameters.
 */
class MetaWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(private string $token, private string $phoneNumberId, private string $baseUrl) {}

    public function sendTemplate(string $to, string $template, string $language, array $parameters): void
    {
        $response = Http::withToken($this->token)->acceptJson()->timeout(15)->post(
            rtrim($this->baseUrl, '/').'/'.$this->phoneNumberId.'/messages',
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $language],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters),
                    ]],
                ],
            ],
        );

        if ($response->failed()) {
            throw new SmsFailed('WhatsApp: '.($response->json('error.message') ?? $response->status()));
        }
    }
}
