<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | SMS to guardians. Driver: "log" (development), "unifonic" or "taqnyat".
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'unifonic' => [
            'app_sid' => env('UNIFONIC_APP_SID'),
            'sender_id' => env('UNIFONIC_SENDER_ID'),
            'url' => env('UNIFONIC_URL', 'https://el.cloud.unifonic.com/rest/SMS/messages'),
        ],
        'taqnyat' => [
            'token' => env('TAQNYAT_TOKEN'),
            'sender' => env('TAQNYAT_SENDER'),
            'url' => env('TAQNYAT_URL', 'https://api.taqnyat.sa/v1/messages'),
        ],
    ],

    /*
    | WhatsApp to guardians. Driver: "log" (development) or "meta" (WhatsApp
    | Cloud API). The template is created in WhatsApp Manager with four body
    | parameters: school, student, status, date.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'attendance_template' => env('WHATSAPP_ATTENDANCE_TEMPLATE', 'attendance_alert'),
        'admission_template' => env('WHATSAPP_ADMISSION_TEMPLATE', 'admission_update'),
        'meta' => [
            'token' => env('WHATSAPP_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'url' => env('WHATSAPP_URL', 'https://graph.facebook.com/v21.0'),
        ],
    ],

    /*
    | Server-side PDF (bulk report cards). Driver: "none" (browser printing
    | only) or "gotenberg".
    */
    'pdf' => [
        'driver' => env('PDF_DRIVER', 'none'),
        'gotenberg_url' => env('GOTENBERG_URL', 'http://127.0.0.1:3000'),
    ],

];
