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

    /*
    | Consulta de RUC (autocompletar empresas). Drivers:
    |   openruc   gratis, sin token. Con DECOLECTA_TOKEN, los RUC que no son 20
    |             (personas naturales) se consultan en Decolecta.
    |   decolecta todos los RUC en Decolecta (gasta el cupo mensual)
    |   ninguno   sin consulta; los datos se escriben a mano
    */
    'ruc' => [
        'driver' => env('RUC_DRIVER', 'openruc'),
    ],

    /*
    | Consulta de DNI (autocompletar trabajadores): decolecta | ninguno.
    | Sin token, el botón aparece desactivado.
    */
    'dni' => [
        'driver' => env('DNI_DRIVER', 'decolecta'),
    ],

    // Token del plan gratuito de Decolecta (decolecta.com/profile), compartido por RUC y DNI.
    'decolecta' => [
        'token' => env('DECOLECTA_TOKEN'),
    ],

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

];
