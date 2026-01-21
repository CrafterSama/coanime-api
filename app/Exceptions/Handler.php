<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        \LaravelJsonApi\Core\Exceptions\JsonApiException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(
            \LaravelJsonApi\Exceptions\ExceptionParser::make()->renderable()
        );

        $this->reportable(function (Throwable $e) {
            // Registrar errores críticos en activity log si hay un request disponible
            if (request() && ! $e instanceof \Illuminate\Validation\ValidationException) {
                try {
                    $request = request();
                    activity()
                        ->withProperties([
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'method' => $request->method(),
                            'url' => $request->fullUrl(),
                            'path' => $request->path(),
                            'error_type' => get_class($e),
                            'error_message' => $e->getMessage(),
                            'route' => $request->route()?->getName(),
                            'status' => 'error',
                        ])
                        ->log("Error no manejado: {$e->getMessage()}");
                } catch (\Exception $loggingException) {
                    // Si falla el logging, no hacer nada para evitar loops infinitos
                }
            }
        });
    }
}
