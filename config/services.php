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

    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realm' => env('KEYCLOAK_REALM'),
        'realms' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'admin_realm' => env('KEYCLOAK_ADMIN_REALM', env('KEYCLOAK_REALM')),
        'admin_client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID', env('KEYCLOAK_CLIENT_ID')),
        'admin_client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET', env('KEYCLOAK_CLIENT_SECRET')),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'sync_users' => env('KEYCLOAK_SYNC_USERS', true),
        'guzzle' => [
            'verify' => env('KEYCLOAK_SSL_VERIFY', true),
        ],
    ],

];
