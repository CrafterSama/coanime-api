<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'login',
        'logout',
        'register',
        'forgot-password',
        'reset-password',
        'password.email',
        'password.update',
        'api/*',
        'external/*',
        'internal/*',
        'sanctum/csrf-cookie',
    ];
}
