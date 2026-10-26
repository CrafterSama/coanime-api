<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BroadcastService;
use App\Support\MalJikan;
use Tests\TestCase;
use Throwable;

class MalJikanSmokeTest extends TestCase
{
    public function test_schedule_search_and_season_hit_mal(): void
    {
        try {
            $schedule = app(BroadcastService::class)->getTodaySchedule();
            $search = MalJikan::search('Naruto', 'tv');
            $season = MalJikan::season((int) date('Y'), 'winter');
        } catch (Throwable $e) {
            $this->markTestSkipped('MAL scrape unavailable: '.$e->getMessage());
        }

        $this->assertIsArray($schedule);
        $this->assertIsArray($search);
        $this->assertIsArray($season);

        if ($schedule !== []) {
            $this->assertArrayHasKey('title', $schedule[0]);
            $this->assertArrayHasKey('url', $schedule[0]);
        }
    }
}
