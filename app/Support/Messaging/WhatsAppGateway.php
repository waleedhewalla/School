<?php

namespace App\Support\Messaging;

interface WhatsAppGateway
{
    /**
     * Send a pre-approved template message (WhatsApp only allows templates
     * for messages the business starts). $to is international without "+".
     *
     * @param  list<string>  $parameters  body placeholders {{1}}, {{2}}, … in order
     *
     * @throws SmsFailed when the provider rejects the message
     */
    public function sendTemplate(string $to, string $template, string $language, array $parameters): void;
}
