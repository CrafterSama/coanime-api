<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(LoginRequest $request)
    {
        try {
            $email = $request->input('email');
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Validar rate limiting
            $request->ensureIsNotRateLimited();

            // Intentar autenticación con JWT
            $credentials = $request->only('email', 'password');
            $token = JWTAuth::attempt($credentials);

            if (!$token) {
                // Incrementar rate limiter en caso de fallo
                \Illuminate\Support\Facades\RateLimiter::hit($request->throttleKey());

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            // Limpiar rate limiter después de éxito
            \Illuminate\Support\Facades\RateLimiter::clear($request->throttleKey());

            // Obtener el usuario autenticado
            $user = JWTAuth::user();

            // Registrar evento de login exitoso
            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'email' => $email,
                    'status' => 'success',
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Inició sesión exitosamente');

            // Retornar token y usuario
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60, // en segundos
                'user' => $user->load('roles'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Registrar intento de login fallido
            activity()
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => $request->input('email'),
                    'status' => 'failed',
                    'error_type' => 'validation',
                    'errors' => $e->errors(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Intento de inicio de sesión fallido');

            throw $e;
        } catch (\Exception $e) {
            // Registrar error inesperado en login
            activity()
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => $request->input('email'),
                    'status' => 'error',
                    'error_type' => 'exception',
                    'error_message' => $e->getMessage(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Error al intentar iniciar sesión');

            \Illuminate\Support\Facades\Log::error('Error en login', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try {
            // Obtener el usuario del token JWT
            $user = JWTAuth::parseToken()->authenticate();

            // Registrar evento de logout antes de cerrar la sesión
            if ($user) {
                activity()
                    ->causedBy($user)
                    ->withProperties([
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ])
                    ->log('Cerro sesión');
            }

            // Invalidar el token JWT
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Sesión cerrada exitosamente']);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Token inválido'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expirado'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cerrar sesión'], 500);
        }
    }
}