<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FixPostsMysqlSchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_idempotent_and_leaves_mal_news_id(): void
    {
        $this->artisan('posts:fix-mysql-schema')->assertSuccessful();
        $this->artisan('posts:fix-mysql-schema')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('posts', 'mal_news_id'));
        $this->assertTrue(Schema::hasColumn('posts', 'postponed_to'));
    }
}
