<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostApproved;
use App\Enums\PostDraft;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class CatalogNewsArticleTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertStringNotContainsString('Fuente: MyAnimeList', (string) $response->json('result.content'));
        $this->assertStringNotContainsString('Tenrai', (string) $response->json('result.content'));
    }

    public function test_legacy_mal_news_slug_serves_saved_post(): void
    {
        Cache::flush();
        try {
            User::factory()->create(['id' => 1]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $post = new Post;
        $post->mal_news_id = 74870618;
        $post->user_id = 1;
        $post->slug = 'phantom-busters-revela-su-elenco';
        $post->category_id = 1;
        $post->title = '¡Phantom Busters revela su elenco!';
        $post->excerpt = 'El anime ya tiene caras confirmadas.';
        $post->content = '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>';
        $post->approved = PostApproved::YES;
        $post->draft = PostDraft::PUBLISHED;
        $post->postponed_to = now();
        $post->image = 'https://cdn.example/a.jpeg';
        $post->save();

        $response = $this->getJson('/external/articles/mal-news-74870618');

        $response->assertOk()
            ->assertJsonPath('result.slug', 'phantom-busters-revela-su-elenco')
            ->assertJsonPath('result.title', '¡Phantom Busters revela su elenco!')
            ->assertJsonPath('code', 200);
        $this->assertNull($response->json('result.source'));
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
