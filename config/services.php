<?php

declare(strict_types=1);

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
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'catalog' => [
        'url' => env('CATALOG_URL', 'https://api.tenrai.org/v1'),
        'key' => env('CATALOG_KEY'),
    ],

    'editorial' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'enabled' => filter_var(env('EDITORIAL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'news_links' => [
        'frontend_base' => env('FRONTEND_URL', 'https://coanime.net'),
        'title' => '/ecma/titulos/{type}/{slug}',
        'person' => '/ecma/personas/{slug}',
        'magazine' => '/ecma/revistas/{slug}',
        'company' => '/ecma/entidades/{slug}',
        'similarity' => (int) env('NEWS_LINK_SIMILARITY', 90),
    ],

];
