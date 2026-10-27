<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostApproved;
use App\Enums\PostDraft;
use App\Models\Post;
use App\Models\User;
use App\Support\CatalogNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class CatalogNewsFillSavedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_fill_puts_saved_spanish_posts_before_english_tenrai(): void
    {
        try {
            User::factory()->create(['id' => 1]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $post = new Post;
        $post->mal_news_id = 99;
        $post->user_id = 1;
        $post->slug = 'mal-news-99';
        $post->category_id = 1;
        $post->title = '¡Noticia persistida!';
        $post->excerpt = 'Texto en español.';
        $post->content = '<p>Los fans ya pueden leer esto en español.</p>';
        $post->approved = PostApproved::YES;
        $post->draft = PostDraft::PUBLISHED;
        $post->postponed_to = now();
        $post->image = 'https://cdn.example/a.jpeg';
        $post->save();

        Http::fake(function (Request $request) {
            return Http::response(['data' => [[
                'mal_id' => 1,
                'title' => 'English leftover',
                'excerpt' => 'The official website',
                'date' => now()->toIso8601String(),
                'images' => ['jpg' => ['image_url' => 'https://cdn.example/b.jpeg']],
            ]]], 200);
        });

        $mixed = CatalogNews::fill([
            ['id' => 10, 'title' => 'Local vieja', 'slug' => 'local-vieja'],
            ['id' => 11, 'title' => 'Otra local', 'slug' => 'otra-local'],
        ], 3);

        $this->assertSame('mal-news-99', $mixed[0]->slug);
        $this->assertSame('¡Noticia persistida!', $mixed[0]->title);
        $this->assertSame('local-vieja', $mixed[1]['slug']);
        $this->assertSame('otra-local', $mixed[2]['slug']);
    }
}
