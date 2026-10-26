<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register()
    {
        Role::findOrCreate('user', 'web');

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Str0ng!Passw0rd-X9',
            'password_confirmation' => 'Str0ng!Passw0rd-X9',
        ]);

        $response->assertCreated()->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);
    }
}
