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
        env('FRONTEND_URL', 'https://coanime.net'),
        'https://coanime.net',
        'https://www.coanime.net',
        'http://front.coanime.net:3000',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ]),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['X-CSRF-Token', 'X-Requested-With', 'Accept', 'Accept-Version', 'Content-Length', 'Content-MD5', 'Content-Type', 'Date', 'X-Api-Version', 'Authorization'],
    'supports_credentials' => true,
    'allowed_origins_patterns' => [],
    'exposed_headers' => [],
    'max_age' => 0,

];