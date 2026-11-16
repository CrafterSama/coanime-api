<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Title;
use App\Services\NewsEntityFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class NewsEntityFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_similar_title_instead_of_missing(): void
    {
        try {
            Title::withoutEvents(function () {
                Title::query()->create([
                    'name' => 'Oshi no Ko',
                    'slug' => 'oshi-no-ko',
                    'user_id' => 1,
                    'type_id' => 1,
                    'rating_id' => 7,
                    'episodies' => 0,
                    'status' => 'Finalizado',
                    'just_year' => 'false',
                    'other_titles' => '',
                ]);
            });
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $found = app(NewsEntityFinder::class)->findTitle('Oshi no Ko!');
        $this->assertNotNull($found);
        $this->assertSame('oshi-no-ko', $found->slug);
        $this->assertSame(1, Title::query()->count());
    }
}
