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

    // AI product writer (App\Services\Ai). Models are comma-separated fallbacks, tried
    // in order while Google is overloaded. Image generation needs billing on the Google project.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash,gemini-3.8-flash'),
        // 3.1 first: 2.5 Flash Image is cheaper but just copies a reference photo and ignores the line-art background.
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image,gemini-2.5-flash-image'),
        // Product photos (AI writer): Nano Banana 2 only, it draws the branded line-art background.
        'product_image_model' => env('GEMINI_PRODUCT_IMAGE_MODEL', 'gemini-3.1-flash-image'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 90),
        'image_timeout' => (int) env('GEMINI_IMAGE_TIMEOUT', 180),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
