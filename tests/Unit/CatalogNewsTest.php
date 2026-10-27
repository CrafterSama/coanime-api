<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CatalogNews;
use App\Support\TitleCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogNewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_slug_roundtrip(): void
    {
        $this->assertSame('mal-news-74870618', CatalogNews::slug(74870618));
        $this->assertSame(74870618, CatalogNews::parseSlug('mal-news-74870618'));
        $this->assertNull(CatalogNews::parseSlug('phantom-busters'));
        $this->assertNull(CatalogNews::parseSlug('mal-news-'));
    }

    public function test_as_relevant_maps_tenrai_item(): void
    {
        $mapped = CatalogNews::asRelevant($this->listItem(), $this->category());

        $this->assertSame(74870618, $mapped['id']);
        $this->assertSame('mal-news-74870618', $mapped['slug']);
        $this->assertSame("'Phantom Busters' Announces Main Cast, Staff", $mapped['title']);
        $this->assertSame('https://cdn.example/a.jpeg', $mapped['image']);
        $this->assertSame('yes', $mapped['approved']);
        $this->assertSame('0', $mapped['draft']);
        $this->assertSame('tenrai', $mapped['source']);
        $this->assertSame('Redacción Coanime', $mapped['users']['name']);
        $this->assertSame([], $mapped['tags']);
        $this->assertSame($this->category(), $mapped['categories']);
        $this->assertSame(0, $mapped['view_counter']);
    }

    public function test_as_article_adds_html_and_attribution(): void
    {
        $mapped = CatalogNews::asArticle($this->detailItem(), $this->category());

        $this->assertSame('mal-news-74870618', $mapped['slug']);
        $this->assertStringContainsString('Phantom Busters', $mapped['content']);
        $this->assertStringContainsString('Fuente: MyAnimeList (Tenrai)', $mapped['content']);
        $this->assertSame([], $mapped['titles']);
    }

    public function test_fill_zero_locals_takes_three_tenrai(): void
    {
        $this->fakeNewsList(3);

        $mixed = CatalogNews::fill([], 3);

        $this->assertCount(3, $mixed);
        $this->assertSame('tenrai', $mixed[0]['source']);
        $this->assertSame('mal-news-1', $mixed[0]['slug']);
        $this->assertSame('mal-news-3', $mixed[2]['slug']);
    }

    public function test_fill_two_locals_takes_one_tenrai(): void
    {
        $this->fakeNewsList(3);

        $local = [
            ['id' => 10, 'title' => 'Local 1', 'slug' => 'local-1'],
            ['id' => 11, 'title' => 'Local 2', 'slug' => 'local-2'],
        ];
        $mixed = CatalogNews::fill($local, 3);

        $this->assertCount(3, $mixed);
        $this->assertSame(10, $mixed[0]['id']);
        $this->assertSame(11, $mixed[1]['id']);
        $this->assertSame('tenrai', $mixed[2]['source']);
        $this->assertSame('mal-news-1', $mixed[2]['slug']);
    }

    public function test_news_hits_catalog_with_limit_and_sfw(): void
    {
        Http::fake(function () {
            return Http::response(['data' => [$this->listItem()]], 200);
        });

        $data = TitleCatalog::news(3);

        $this->assertSame(74870618, $data[0]['mal_id']);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/news')
                && ($request['limit'] ?? null) == 3
                && ($request['sfw'] ?? null) === 'true';
        });
    }

    public function test_news_by_id_returns_null_on_404(): void
    {
        Http::fake(fn () => Http::response(['status' => 404], 404));

        $this->assertNull(TitleCatalog::newsById(1));
    }

    public function test_article_404_is_null(): void
    {
        Http::fake(fn () => Http::response(['status' => 404], 404));

        $this->assertNull(CatalogNews::article(1));
    }

    public function test_as_relevant_uses_llm_editorial_json(): void
    {
        config([
            'services.editorial.key' => 'sk-test',
            'services.editorial.url' => 'https://api.openai.com/v1/chat/completions',
        ]);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'items' => [[
                                'mal_id' => 74870618,
                                'title' => '¡Phantom Busters revela su elenco principal!',
                                'excerpt' => 'El anime ya tiene caras y staff confirmados.',
                            ]],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
        ]);

        $mapped = CatalogNews::asRelevant($this->listItem(), $this->category());

        $this->assertSame('¡Phantom Busters revela su elenco principal!', $mapped['title']);
        $this->assertSame('El anime ya tiene caras y staff confirmados.', $mapped['excerpt']);
        $this->assertSame('Redacción Coanime', $mapped['users']['name']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'chat/completions'));
    }

    public function test_as_article_uses_llm_html(): void
    {
        config([
            'services.editorial.key' => 'sk-test',
            'services.editorial.url' => 'https://api.openai.com/v1/chat/completions',
        ]);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => '¡Phantom Busters ya tiene elenco!',
                            'excerpt' => 'Se confirman voces y staff.',
                            'content' => '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p><h4>Elenco</h4><p>Nobuhiko Okamoto encabeza el reparto.</p>',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
        ]);

        $mapped = CatalogNews::asArticle($this->detailItem(), $this->category());

        $this->assertSame('¡Phantom Busters ya tiene elenco!', $mapped['title']);
        $this->assertStringContainsString('Los fans de Phantom Busters', $mapped['content']);
        $this->assertStringContainsString('<h4>Elenco</h4>', $mapped['content']);
        $this->assertStringContainsString('Fuente: MyAnimeList (Tenrai)', $mapped['content']);
    }

    public function test_llm_error_falls_back_to_source_text(): void
    {
        config([
            'services.editorial.key' => 'sk-test',
            'services.editorial.url' => 'https://api.openai.com/v1/chat/completions',
        ]);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => 'nope'], 500),
        ]);

        $mapped = CatalogNews::asRelevant($this->listItem(), $this->category());

        $this->assertSame("'Phantom Busters' Announces Main Cast, Staff", $mapped['title']);
        $this->assertSame('Redacción Coanime', $mapped['users']['name']);
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function category(): array
    {
        return ['id' => 1, 'name' => 'Noticias', 'slug' => 'noticias'];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(): array
    {
        return [
            'mal_id' => 74870618,
            'url' => 'https://myanimelist.net/news/74870618',
            'title' => "'Phantom Busters' Announces Main Cast, Staff",
            'date' => '2026-10-27T09:39:33+00:00',
            'author_username' => 'Syureria',
            'images' => ['jpg' => ['image_url' => 'https://cdn.example/a.jpeg']],
            'comments' => 0,
            'excerpt' => 'The official website for Phantom Busters...',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailItem(): array
    {
        return $this->listItem() + [
            'content' => [
                'html' => '<p>The official website for Phantom Busters manga revealed the main cast.</p>',
                'markdown' => 'The official website for Phantom Busters.',
            ],
            'relations' => ['anime' => [], 'manga' => []],
        ];
    }

    private function fakeNewsList(int $count): void
    {
        $data = [];
        for ($i = 1; $i <= $count; $i++) {
            $item = $this->listItem();
            $item['mal_id'] = $i;
            $item['title'] = 'News '.$i;
            $data[] = $item;
        }

        Http::fake(function (Request $request) use ($data) {
            if (preg_match('#/news/(\d+)#', $request->url())) {
                return Http::response(['data' => $this->detailItem()], 200);
            }

            return Http::response(['data' => $data], 200);
        });
    }
}
