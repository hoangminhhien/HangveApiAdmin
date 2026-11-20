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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'gg-translate' => [
        'endpoint' => env('GOOGLE_TRANSLATE_URL', 'https://www.googleapis.com/language/translate/v2?key=%s&q=%s&target=%s'),
        'endpoint_text' => env('GOOGLE_TRANSLATE_URL', 'https://www.googleapis.com/language/translate/v2?key=%s'),
        'key' => env('GOOGLE_TRANSLATE_KEY', 'AIzaSyCPOq7e79Gg0Xs_Fx7rqiJI0Yr6ugTvH-U')
    ]

];
