<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    /**
     * Rutas que deben ser excluidas del registro.
     *
     * @var array<string>
     */
    protected array $excludedRoutes = [
        'sanctum.csrf-cookie',
        'health',
        'ping',
        'metrics',
        'debugbar',
    ];

    /**
     * Patrones de URL que deben ser excluidos.
     *
     * @var array<string>
     */
    protected array $excludedPatterns = [
        'health',
        'ping',
        'metrics',
        'debugbar',
        'telescope',
    ];

    /**
     * Métodos HTTP que deben ser registrados.
     *
     * @var array<string>
     */
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si esta ruta debe ser excluida
        if ($this->shouldExcludeRoute($request)) {
            return $next($request);
        }

        // Medir tiempo de inicio
        $startTime = microtime(true);

        try {
            // Procesar la petición
            $response = $next($request);

            // Calcular tiempo de respuesta
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            // Determinar si fue exitoso o error basado en el código de estado
            $statusCode = $response->getStatusCode();
            $isSuccess = $statusCode >= 200 && $statusCode < 400;

            // Registrar la actividad
            $this->logActivity($request, $response, $responseTime, $isSuccess);

            return $response;
        } catch (\Exception $e) {
            // Calcular tiempo de respuesta incluso en caso de error
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            // Registrar error
            $this->logError($request, $e, $responseTime);

            throw $e;
        }
    }

    /**
     * Determina si la ruta debe ser excluida del registro.
     */
    protected function shouldExcludeRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        $path = $request->path();
        $method = $request->method();

        // Excluir métodos no permitidos
        if (! in_array($method, $this->allowedMethods)) {
            return true;
        }

        // Excluir rutas específicas por nombre
        if ($routeName && in_array($routeName, $this->excludedRoutes)) {
            return true;
        }

        // Excluir por patrón en la URL
        foreach ($this->excludedPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra la actividad del usuario.
     */
    protected function logActivity(Request $request, Response $response, int $responseTime, bool $isSuccess = true): void
    {
        try {
            $user = $request->user();
            $routeName = $request->route()?->getName();
            $action = $this->determineAction($request);
            $statusCode = $response->getStatusCode();

            // Preparar datos de la petición (excluir información sensible)
            $requestData = $this->prepareRequestData($request);

            // Agregar prefijo según el resultado
            $logMessage = $isSuccess ? $action : "Error: {$action}";

            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $this->getClientIp($request),
                    'user_agent' => $request->userAgent(),
                    'method' => $request->method(),
                    'route' => $routeName,
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'request_data' => $requestData,
                    'status_code' => $statusCode,
                    'response_time' => $responseTime,
                    'status' => $isSuccess ? 'success' : 'error',
                    'controller' => $request->route()?->getControllerClass(),
                    'method_name' => $request->route()?->getActionMethod(),
                ])
                ->log($logMessage);
        } catch (\Exception $e) {
            // Si falla el registro, loguear en el sistema de logs de Laravel
            \Illuminate\Support\Facades\Log::error('Error al registrar actividad del usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Registra errores en las peticiones.
     */
    protected function logError(Request $request, \Exception $exception, int $responseTime): void
    {
        try {
            $user = $request->user();
            $routeName = $request->route()?->getName();
            $action = $this->determineAction($request);

            // Preparar datos de la petición (excluir información sensible)
            $requestData = $this->prepareRequestData($request);

            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $this->getClientIp($request),
                    'user_agent' => $request->userAgent(),
                    'method' => $request->method(),
                    'route' => $routeName,
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'request_data' => $requestData,
                    'status' => 'error',
                    'error_type' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                    'response_time' => $responseTime,
                    'controller' => $request->route()?->getControllerClass(),
                    'method_name' => $request->route()?->getActionMethod(),
                ])
                ->log("Error: {$action} - {$exception->getMessage()}");
        } catch (\Exception $e) {
            // Si falla el registro, loguear en el sistema de logs de Laravel
            \Illuminate\Support\Facades\Log::error('Error al registrar error de actividad', [
                'original_error' => $exception->getMessage(),
                'logging_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determina la acción basada en la ruta y el método HTTP.
     */
    protected function determineAction(Request $request): string
    {
        $method = $request->method();
        $routeName = $request->route()?->getName();
        $path = $request->path();

        // Intentar determinar la acción desde el nombre de la ruta
        if ($routeName) {
            // Formato: resource.action (ej: posts.index, titles.show)
            $parts = explode('.', $routeName);
            if (count($parts) >= 2) {
                $resource = $parts[0];
                $action = $parts[1];

                return match ($action) {
                    'index' => "Listó {$resource}",
                    'show' => "Vió {$resource}",
                    'store' => "Creó {$resource}",
                    'update' => "Actualizó {$resource}",
                    'destroy' => "Eliminó {$resource}",
                    default => ucfirst($action).' '.$resource,
                };
            }

            return ucfirst($routeName);
        }

        // Determinar acción por método HTTP y ruta
        $pathParts = explode('/', trim($path, '/'));

        return match ($method) {
            'GET' => $this->getGetAction($pathParts),
            'POST' => $this->getPostAction($pathParts),
            'PUT', 'PATCH' => $this->getUpdateAction($pathParts),
            'DELETE' => $this->getDeleteAction($pathParts),
            default => "{$method} {$path}",
        };
    }

    /**
     * Determina la acción para peticiones GET.
     */
    protected function getGetAction(array $pathParts): string
    {
        if (empty($pathParts)) {
            return 'Visitó la página principal';
        }

        $resource = $pathParts[0] ?? 'unknown';
        $id = $pathParts[1] ?? null;

        return $id ? "Vió {$resource}" : "Listó {$resource}";
    }

    /**
     * Determina la acción para peticiones POST.
     */
    protected function getPostAction(array $pathParts): string
    {
        if (empty($pathParts)) {
            return 'Creó recurso';
        }

        $resource = $pathParts[0] ?? 'unknown';

        return "Creó {$resource}";
    }

    /**
     * Determina la acción para peticiones PUT/PATCH.
     */
    protected function getUpdateAction(array $pathParts): string
    {
        if (empty($pathParts)) {
            return 'Actualizó recurso';
        }

        $resource = $pathParts[0] ?? 'unknown';

        return "Actualizó {$resource}";
    }

    /**
     * Determina la acción para peticiones DELETE.
     */
    protected function getDeleteAction(array $pathParts): string
    {
        if (empty($pathParts)) {
            return 'Eliminó recurso';
        }

        $resource = $pathParts[0] ?? 'unknown';

        return "Eliminó {$resource}";
    }

    /**
     * Prepara los datos de la petición excluyendo información sensible.
     */
    protected function prepareRequestData(Request $request): array
    {
        $data = $request->all();

        // Campos sensibles que no deben registrarse
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_token',
            'secret',
            'credit_card',
            'cvv',
            'ssn',
            'social_security_number',
            '_token',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        // Limitar el tamaño de los datos para evitar problemas con campos muy grandes
        $data = array_map(function ($value) {
            if (is_string($value) && strlen($value) > 500) {
                return substr($value, 0, 500).'... [TRUNCATED]';
            }

            return $value;
        }, $data);

        return $data;
    }

    /**
     * Obtiene la IP real del cliente.
     */
    protected function getClientIp(Request $request): ?string
    {
        $ipAddress = null;

        // Probar varios headers comunes para proxies
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $ip = $request->server($header);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ipAddress = $ip;
                break;
            }
        }

        // Si no se encuentra IP pública, usar REMOTE_ADDR
        if (! $ipAddress) {
            $ipAddress = $request->ip();
        }

        return $ipAddress;
    }
}
