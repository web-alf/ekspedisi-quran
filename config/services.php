<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ngrok' => [
        'url' => env('NGROK_URL'),
    ],

    'monitoring' => [
        'email_alerts' => env('MONITORING_EMAIL_ALERTS', false),
        'webhook_url' => env('MONITORING_WEBHOOK_URL'),
        'alert_email' => env('MONITORING_ALERT_EMAIL'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
    ],

    'storage' => [
        'alert_email' => env('STORAGE_ALERT_EMAIL', 'admin@example.com'),
    ],

];
