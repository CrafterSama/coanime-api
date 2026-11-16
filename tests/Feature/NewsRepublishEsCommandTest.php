<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class NewsRepublishEsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_republish_clears_stale_english_and_saves_spanish(): void
    {
        try {
            User::factory()->create(['id' => 1]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
        ]);

        Cache::put('catalog.news.editorial.article.74870618', [
            'title' => "'Phantom Busters' Announces Main Cast, Staff",
            'excerpt' => 'The official website for Phantom Busters...',
            'content' => '<p>The official website announced the main cast.</p>',
            'source' => 'translate',
        ], 86400);
        Cache::put('catalog.news.relevants.3', [['title' => 'English leftover']], 1800);

        $list = [[
            'mal_id' => 74870618,
            'title' => "'Phantom Busters' Announces Main Cast, Staff",
            'date' => '2026-10-27T09:39:33+00:00',
            'excerpt' => 'The official website for Phantom Busters...',
            'images' => ['jpg' => ['image_url' => 'https://cdn.example/a.jpeg']],
        ]];
        $detail = $list[0] + [
            'content' => ['html' => '<p>The official website announced the main cast.</p>'],
        ];
        $llm = [
            'title' => '¡Phantom Busters revela su elenco!',
            'excerpt' => 'El anime ya tiene caras confirmadas.',
            'content' => '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>',
        ];

        Http::fake(function (Request $request) use ($list, $detail, $llm) {
            if (str_contains($request->url(), 'generateContent')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => json_encode($llm, JSON_UNESCAPED_UNICODE)]]]]],
                ], 200);
            }
            if (preg_match('#/news/(\d+)#', $request->url())) {
                return Http::response(['data' => $detail], 200);
            }

            return Http::response(['data' => $list], 200);
        });

        Cache::put('catalog.news.relevants.1', [['title' => 'EN']], 1800);

        $this->artisan('news:republish-es', ['--limit' => 1])->assertSuccessful();

        $this->assertFalse(Cache::has('catalog.news.editorial.article.74870618'));
        $this->assertFalse(Cache::has('catalog.news.relevants.3'));
        $this->assertFalse(Cache::has('catalog.news.relevants.1'));
        $this->assertSame(1, Post::count());
        $post = Post::query()->first();
        $this->assertSame('¡Phantom Busters revela su elenco!', $post->title);
        $this->assertSame('mal-news-74870618', $post->slug);
    }
}
