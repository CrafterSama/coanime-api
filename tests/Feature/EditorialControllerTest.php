<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EditorialManual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

class EditorialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_and_publish_manual(): void
    {
        try {
            Role::findOrCreate('administrator', 'web');
            $admin = User::factory()->create();
            $admin->assignRole('administrator');
            $other = User::factory()->create();
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $this->getJson('/internal/editorial')->assertUnauthorized();
        $this->actingAs($other, 'api')->getJson('/internal/editorial')->assertForbidden();

        $this->actingAs($admin, 'api')
            ->getJson('/internal/editorial')
            ->assertOk()
            ->assertJsonPath('payload.medium_name', 'Coanime');

        $payload = EditorialManual::defaultPayload();
        $payload['declaration'] = 'Nueva declaración de prueba.';

        $this->actingAs($admin, 'api')
            ->postJson('/internal/editorial', $payload)
            ->assertCreated()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('payload.declaration', 'Nueva declaración de prueba.');

        $this->assertSame(1, EditorialManual::query()->count());
        $this->assertStringContainsString('Nueva declaración', EditorialManual::systemPrompt());

        $this->actingAs($admin, 'api')
            ->getJson('/internal/editorial/versions')
            ->assertOk()
            ->assertJsonCount(1, 'versions');
    }
}
