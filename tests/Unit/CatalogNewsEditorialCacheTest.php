<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CatalogNewsEditorial;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogNewsEditorialCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_article_does_not_write_24h_editorial_cache(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
        ]);

        $llm = [
            'title' => '¡Phantom Busters revela su elenco!',
            'excerpt' => 'El anime ya tiene caras confirmadas.',
            'content' => '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>',
        ];
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($llm, JSON_UNESCAPED_UNICODE)]]]]],
        ], 200));

        $row = CatalogNewsEditorial::article(
            74870618,
            "'Phantom Busters' Announces Main Cast, Staff",
            'The official website for Phantom Busters...',
            '<p>The official website announced the main cast.</p>'
        );

        $this->assertSame('¡Phantom Busters revela su elenco!', $row['title']);
        $this->assertFalse(Cache::has('catalog.news.editorial.article.74870618'));
        $this->assertFalse(Cache::has('catalog.news.editorial.card.74870618'));
    }

    public function test_cards_does_not_write_editorial_card_cache(): void
    {
        config([
            'services.editorial.key' => '',
            'services.editorial.enabled' => true,
        ]);

        $out = CatalogNewsEditorial::cards([[
            'mal_id' => 99,
            'title' => 'English title',
            'excerpt' => 'English excerpt',
        ]]);

        $this->assertArrayHasKey(99, $out);
        $this->assertFalse(Cache::has('catalog.news.editorial.card.99'));
    }

    public function test_forget_news_caches_clears_known_keys(): void
    {
        Cache::put('catalog.news.editorial.card.7', ['title' => 'x', 'excerpt' => 'y'], 3600);
        Cache::put('catalog.news.editorial.article.7', ['title' => 'x', 'excerpt' => 'y', 'content' => 'z'], 3600);
        Cache::put('catalog.news.article.7', ['slug' => 'mal-news-7'], 3600);
        Cache::put('catalog.news.i18n.7.title', 'EN', 3600);
        Cache::put('catalog.news.i18n.7.excerpt', 'EN', 3600);
        Cache::put('catalog.news.i18n.7.content', 'EN', 3600);

        CatalogNewsEditorial::forgetNewsCaches(7);

        $this->assertFalse(Cache::has('catalog.news.editorial.card.7'));
        $this->assertFalse(Cache::has('catalog.news.editorial.article.7'));
        $this->assertFalse(Cache::has('catalog.news.article.7'));
        $this->assertFalse(Cache::has('catalog.news.i18n.7.title'));
        $this->assertFalse(Cache::has('catalog.news.i18n.7.excerpt'));
        $this->assertFalse(Cache::has('catalog.news.i18n.7.content'));
    }
}
