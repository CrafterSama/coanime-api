<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BroadcastService;
use App\Support\TitleCatalog;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Throwable;

class TitleCatalogSmokeTest extends TestCase
{
    public function test_schedule_search_and_season_hit_catalog(): void
    {
        Cache::forget('catalog.schedule.'.strtolower(date('l')));

        try {
            $raw = TitleCatalog::schedule(date('l'));
            $schedule = app(BroadcastService::class)->getTodaySchedule();
            $search = TitleCatalog::search('Naruto', 'tv');
            $season = TitleCatalog::season((int) date('Y'), 'winter');
        } catch (Throwable $e) {
            $this->markTestSkipped('Catalog unavailable: '.$e->getMessage());
        }

        $this->assertNotEmpty($raw);
        $this->assertNotEmpty($schedule);
        $this->assertIsArray($search);
        $this->assertIsArray($season);
        $this->assertArrayHasKey('title', $schedule[0]);
        $this->assertArrayHasKey('url', $schedule[0]);
        $this->assertArrayHasKey('images', $schedule[0]);
    }
}
