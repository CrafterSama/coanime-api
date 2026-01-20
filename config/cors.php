<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'external/*', 'internal/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'https://coanime.net:3000'),
        'https://coanime.net:3000',
        'https://coanime.net',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ]),
    'supports_credentials' => true,
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'allowed_origins_patterns' => [],
    'exposed_headers' => [],
    'max_age' => 0,


];