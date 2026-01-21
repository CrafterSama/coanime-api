<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            $email = $request->input('email');
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            $request->validate([
                'email' => ['required', 'email'],
            ]);

            // We will send the password reset link to this user. Once we have attempted
            // to send the link, we will examine the response then see the message we
            // need to show to the user. Finally, we'll send out a proper response.
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status != Password::RESET_LINK_SENT) {
                // Registrar intento fallido de solicitud de reset
                activity()
                    ->withProperties([
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'email' => $email,
                        'status' => 'failed',
                        'error_type' => 'password_reset',
                        'error_message' => __($status),
                        'route' => $request->route()?->getName(),
                        'url' => $request->fullUrl(),
                    ])
                    ->log('Intento de solicitud de reset de contraseña fallido');

                throw ValidationException::withMessages([
                    'email' => [__($status)],
                ]);
            }

            // Registrar solicitud exitosa de reset
            activity()
                ->withProperties([
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'email' => $email,
                    'status' => 'success',
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Solicitud de reset de contraseña enviada exitosamente');

            return response()->json(['status' => __($status)]);
        } catch (ValidationException $e) {
            // Registrar error de validación
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
                ->log('Error de validación en solicitud de reset de contraseña');

            return response()->json(['errors' => $e->validator->errors()->all()], 422);
        } catch (\Exception $e) {
            // Registrar error inesperado
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
                ->log('Error al procesar solicitud de reset de contraseña');

            \Illuminate\Support\Facades\Log::error('Error en PasswordResetLinkController', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
