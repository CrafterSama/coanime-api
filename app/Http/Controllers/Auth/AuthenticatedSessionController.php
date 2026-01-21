<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            // Intentar autenticación
            $request->authenticate();

            $request->session()->regenerate();

            $user = Auth::user();

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

            return response()->noContent();
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
        $user = Auth::user();

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

        Auth::guard('web')->logout();

        $request->session()->flush();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
