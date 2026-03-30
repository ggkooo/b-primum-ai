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

    'ollama' => [
        'api_key' => env('OLLAMA_API_KEY'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'auth_header' => env('OLLAMA_AUTH_HEADER', 'x-api-key'),
        'verify_ssl' => env('OLLAMA_VERIFY_SSL', true),
        'ca_bundle' => env('OLLAMA_CA_BUNDLE'),
        'timeout' => env('OLLAMA_TIMEOUT', 0),
        'connect_timeout' => env('OLLAMA_CONNECT_TIMEOUT', 0),
        'generate_embeddings' => env('OLLAMA_GENERATE_EMBEDDINGS', false),
    ],

];
