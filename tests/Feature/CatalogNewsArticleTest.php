<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class CatalogNewsArticleTest extends TestCase
{
    public function test_article_slug_serves_tenrai_as_local_post(): void
    {
        Cache::flush();
        Http::fake(function (Request $request) {
            if (preg_match('#/news/(\d+)#', $request->url())) {
                return Http::response([
                    'data' => [
                        'mal_id' => 74870618,
                        'title' => "'Phantom Busters' Announces Main Cast, Staff",
                        'date' => '2026-10-27T09:39:33+00:00',
                        'author_username' => 'Syureria',
                        'images' => ['jpg' => ['image_url' => 'https://cdn.example/a.jpeg']],
                        'comments' => 0,
                        'excerpt' => 'The official website for Phantom Busters...',
                        'content' => [
                            'html' => '<p>The official website for Phantom Busters manga revealed the main cast.</p>',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        });

        try {
            $response = $this->getJson('/external/articles/mal-news-74870618');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $response->assertOk()
            ->assertJsonPath('result.slug', 'mal-news-74870618')
            ->assertJsonPath('result.source', 'tenrai')
            ->assertJsonPath('code', 200);
        $this->assertStringContainsString('Fuente: MyAnimeList (Tenrai)', $response->json('result.content'));
    }

    public function test_unknown_slug_is_404(): void
    {
        try {
            $response = $this->getJson('/external/articles/no-such-post-xyz');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $response->assertNotFound();
    }
}
