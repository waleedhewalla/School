<?php

namespace App\Support\Messaging;

interface SmsGateway
{
    /**
     * Send one SMS. $to is an international number without "+", e.g. 9665XXXXXXXX.
     *
     * @throws SmsFailed when the provider rejects the message
     */
    public function send(string $to, string $body): void;
}
