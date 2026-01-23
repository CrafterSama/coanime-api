<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $email = $request->input('email');
            $name = $request->input('name');

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => [
                    'required',
                    'string',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                    'confirmed',
                ],
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($request->password),
            ]);

            $user->assignRole('user');

            event(new Registered($user));

            // Generar token JWT
            $token = JWTAuth::fromUser($user);

            // Registrar evento de registro exitoso
            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'email' => $email,
                    'name' => $name,
                    'status' => 'success',
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Usuario registrado exitosamente');

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60, // en segundos
                'user' => $user->load('roles'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Registrar intento de registro fallido por validación
            activity()
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => $request->input('email'),
                    'name' => $request->input('name'),
                    'status' => 'failed',
                    'error_type' => 'validation',
                    'errors' => $e->errors(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Intento de registro fallido - Validación');

            throw $e;
        } catch (\Exception $e) {
            // Registrar error inesperado en registro
            activity()
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => $request->input('email'),
                    'name' => $request->input('name'),
                    'status' => 'error',
                    'error_type' => 'exception',
                    'error_message' => $e->getMessage(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ])
                ->log('Error al intentar registrar usuario');

            \Illuminate\Support\Facades\Log::error('Error en registro', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
