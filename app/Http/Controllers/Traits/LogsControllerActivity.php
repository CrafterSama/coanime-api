<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait LogsControllerActivity
{
    /**
     * Boot el trait para registrar actividad automáticamente.
     */
    protected static function bootLogsControllerActivity(): void
    {
        // Este método se ejecutará automáticamente cuando el trait sea usado
    }

    /**
     * Registra la actividad cuando se llama a un método del controlador.
     */
    protected function logControllerActivity(string $method, Request $request, $response = null): void
    {
        try {
            $user = Auth::user();
            $controllerName = class_basename(static::class);
            $action = $this->determineAction($method, $controllerName);
            $description = $this->getDescription($controllerName, $method, $request);

            // Obtener información del recurso afectado si está disponible
            $resourceType = null;
            $resourceId = null;

            // Intentar obtener el modelo del request
            if ($request->route()) {
                $routeParameters = $request->route()->parameters();
                foreach ($routeParameters as $key => $value) {
                    // Si el parámetro es un modelo Eloquent
                    if (is_object($value) && method_exists($value, 'getTable')) {
                        $resourceType = get_class($value);
                        $resourceId = $value->id ?? null;
                        break;
                    }
                }
            }

            $activity = activity()
                ->causedBy($user);

            // Solo agregar performedOn si tenemos un recurso válido
            if ($resourceType && $resourceId) {
                $resourceModel = (new $resourceType)::find($resourceId);
                if ($resourceModel) {
                    $activity->performedOn($resourceModel);
                }
            }

            $activity->withProperties([
                    'controller' => static::class,
                    'controller_name' => $controllerName,
                    'method' => $method,
                    'ip_address' => $this->getClientIp($request),
                    'user_agent' => $request->userAgent(),
                    'http_method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'request_data' => $this->prepareRequestData($request),
                    'route_parameters' => $request->route()?->parameters(),
                    'status_code' => $response?->getStatusCode(),
                    'resource_type' => $resourceType ? class_basename($resourceType) : null,
                    'resource_id' => $resourceId,
                ])
                ->log($action);
        } catch (\Exception $e) {
            // Si falla el registro, loguear en el sistema de logs de Laravel
            \Illuminate\Support\Facades\Log::error('Error al registrar actividad del controlador', [
                'controller' => static::class,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determina la acción basada en el método del controlador.
     */
    protected function determineAction(string $method, string $controllerName): string
    {
        $controllerShortName = str_replace('Controller', '', $controllerName);
        $resource = Str::kebab($controllerShortName);

        return match ($method) {
            'index' => "Accedió a la lista de {$resource}",
            'show', 'page', 'showApi' => "Vió {$resource}",
            'create' => "Intentó crear nuevo {$resource}",
            'store' => "Creó nuevo {$resource}",
            'edit' => "Editó {$resource}",
            'update' => "Actualizó {$resource}",
            'destroy', 'delete' => "Eliminó {$resource}",
            'approve', 'approved' => "Aprobó {$resource}",
            default => "Ejecutó {$method} en {$resource}",
        };
    }

    /**
     * Obtiene una descripción detallada del evento.
     */
    protected function getDescription(string $controllerName, string $method, Request $request): string
    {
        $description = "Usuario accedió a {$controllerName}::{$method}";

        if ($request->route()) {
            $parameters = $request->route()->parameters();
            if (! empty($parameters)) {
                $params = implode(', ', array_map(fn ($key, $value) => "{$key}:{$value}", array_keys($parameters), array_values($parameters)));
                $description .= " con parámetros: {$params}";
            }
        }

        return $description;
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

        // Limitar el tamaño de los datos
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
