<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request.
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
                'token' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            // Here we will attempt to reset the user's password. If it is successful we
            // will update the password on an actual user model and persist it to the
            // database. Otherwise we will parse the error and return the response.
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user) use ($request) {
                    $user->forceFill([
                        'password' => Hash::make($request->password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));

                    // Registrar reset exitoso
                    activity()
                        ->causedBy($user)
                        ->withProperties([
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'email' => $user->email,
                            'status' => 'success',
                            'route' => $request->route()?->getName(),
                            'url' => $request->fullUrl(),
                        ])
                        ->log('Contraseña restablecida exitosamente');
                }
            );

            if ($status != Password::PASSWORD_RESET) {
                // Registrar intento fallido de reset
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
                    ->log('Intento de restablecimiento de contraseña fallido');

                throw ValidationException::withMessages([
                    'email' => [__($status)],
                ]);
            }

            return response()->json(['status' => __($status)]);
        } catch (ValidationException $e) {
            // Registrar error de validación
            $errors = method_exists($e, 'errors') ? $e->errors() : [];
            $errorMessages = $e->validator ? $e->validator->errors()->all() : [$e->getMessage()];

            activity()
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => $request->input('email'),
                    'status' => 'failed',
                    'error_type' => 'validation',
                    'errors' => $errors,
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Error de validación en restablecimiento de contraseña');

            return response()->json(['errors' => $errorMessages], 422);
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
                ->log('Error al procesar restablecimiento de contraseña');

            \Illuminate\Support\Facades\Log::error('Error en NewPasswordController', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
