<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Title;
use App\Models\User;
use App\Services\NewsContentLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class NewsContentLinkResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.news_links.frontend_base' => 'https://coanime.net',
            'services.catalog.url' => 'https://api.tenrai.org/v1',
        ]);
    }

    public function test_rewrites_existing_title_link_and_does_not_duplicate(): void
    {
        try {
            User::factory()->create(['id' => 1]);
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

        Http::fake([
            'api.tenrai.org/*' => Http::response([
                'data' => [
                    'mal_id' => 47917,
                    'title' => 'Oshi no Ko',
                    'type' => 'TV',
                    'synopsis' => 'Story [Written by MAL Rewrite]',
                ],
            ], 200),
        ]);

        $html = '<p>Ver <a href="https://myanimelist.net/anime/47917/Oshi_no_Ko">Oshi no Ko</a>.</p>'
            .'<p>Fuente: MyAnimeList (Tenrai)</p>';
        $out = app(NewsContentLinkResolver::class)->resolve($html);

        $this->assertStringContainsString('https://coanime.net/ecma/titulos/tv/oshi-no-ko', $out['html']);
        $this->assertStringNotContainsString('MyAnimeList', $out['html']);
        $this->assertStringNotContainsString('Tenrai', $out['html']);
        $this->assertSame(1, Title::query()->count());
        $this->assertNotEmpty($out['title_ids']);
    }

    public function test_creates_title_stub_without_mal_mentions(): void
    {
        try {
            User::factory()->create(['id' => 1]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        Http::fake([
            'api.tenrai.org/*' => Http::response([
                'data' => [
                    'mal_id' => 1,
                    'title' => 'Cowboy Bebop',
                    'type' => 'TV',
                    'synopsis' => 'Space bounty hunters [Written by MAL Rewrite]',
                ],
            ], 200),
        ]);

        $html = '<p><a href="https://myanimelist.net/anime/1/Cowboy_Bebop">Cowboy Bebop</a></p>';
        $out = app(NewsContentLinkResolver::class)->resolve($html);

        $this->assertSame(1, Title::query()->count());
        $title = Title::query()->first();
        $this->assertSame('Cowboy Bebop', $title->name);
        $this->assertStringNotContainsString('MAL', (string) $title->sinopsis);
        $this->assertStringContainsString('https://coanime.net/ecma/titulos/tv/'.$title->slug, $out['html']);
    }

    public function test_character_link_stays_on_mal(): void
    {
        $html = '<p><a href="https://myanimelist.net/character/1/Spike">Spike</a></p>';
        $out = app(NewsContentLinkResolver::class)->resolve($html);
        $this->assertStringContainsString('myanimelist.net/character/1/Spike', $out['html']);
        $this->assertSame([], $out['title_ids']);
    }
}
