<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }

    public function handle($request, Closure $next, ...$guards)
    {
        // Si no se especifica un guard y es una petición API, usar guard 'api' por defecto
        if (empty($guards) && $request->is('api/*')) {
            $guards = ['api'];
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }
}
