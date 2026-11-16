<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BroadcastService;
use App\Support\TitleCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadcastServiceTest extends TestCase
{
    public function test_day_filter_maps_weekdays(): void
    {
        $this->assertSame('monday', TitleCatalog::dayFilter('Monday'));
        $this->assertSame('sunday', TitleCatalog::dayFilter('sunday'));
        $this->assertNull(TitleCatalog::dayFilter('funday'));
    }

    public function test_coanime_url_uses_slugged_type_and_title(): void
    {
        $this->assertSame(
            'https://coanime.net/ecma/titulos/tv/one-piece',
            BroadcastService::coanimeUrl('TV', 'One Piece')
        );
    }

    public function test_both_getters_keep_v4_images_and_add_url(): void
    {
        Cache::forget('catalog.schedule.monday');

        $item = [
            'mal_id' => 21,
            'title' => 'One Piece',
            'type' => 'TV',
            'score' => 8.7,
            'images' => [
                'jpg' => [
                    'image_url' => 'https://cdn.example/a.jpg',
                    'small_image_url' => 'https://cdn.example/a.t.jpg',
                    'large_image_url' => 'https://cdn.example/a.l.jpg',
                ],
                'webp' => [
                    'image_url' => 'https://cdn.example/a.webp',
                    'small_image_url' => 'https://cdn.example/a.t.webp',
                    'large_image_url' => 'https://cdn.example/a.l.webp',
                ],
            ],
        ];

        Http::fake([
            '*schedules*' => Http::response([
                'pagination' => ['last_visible_page' => 1, 'has_next_page' => false],
                'data' => [$item],
            ], 200),
        ]);

        $today = app(BroadcastService::class)->getTodaySchedule('Monday');
        $byDay = app(BroadcastService::class)->getScheduleByDay('monday');

        $this->assertNotEmpty($today);
        $this->assertSame($today[0]['url'], $byDay[0]['url']);
        $this->assertSame('https://coanime.net/ecma/titulos/tv/one-piece', $today[0]['url']);
        $this->assertArrayHasKey('jpg', $today[0]['images']);
        $this->assertArrayHasKey('webp', $today[0]['images']);
        $this->assertSame('https://cdn.example/a.l.jpg', $today[0]['images']['jpg']['large_image_url']);
    }
}
