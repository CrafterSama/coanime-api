<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\CatalogNewsIngest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class CatalogNewsIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_ingest_creates_spanish_post_for_user_one(): void
    {
        $this->seedOwner();
        $this->fakeTenraiAndLlm(spanish: true);

        $result = app(CatalogNewsIngest::class)->run(1);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, Post::count());
        $post = Post::query()->first();
        $this->assertSame(74870618, (int) $post->mal_news_id);
        $this->assertSame(1, (int) $post->user_id);
        $this->assertSame('mal-news-74870618', $post->slug);
        $this->assertSame('¡Phantom Busters revela su elenco!', $post->title);
        $this->assertTrue($result['editorial']['configured']);
        $this->assertTrue($result['editorial']['connected']);
        $this->assertSame('llm', $result['items'][0]['source']);
    }

    public function test_ingest_does_not_duplicate(): void
    {
        $this->seedOwner();
        $this->fakeTenraiAndLlm(spanish: true);

        app(CatalogNewsIngest::class)->run(1);
        $result = app(CatalogNewsIngest::class)->run(1);

        $this->assertSame(1, Post::count());
        $this->assertSame(1, $result['updated']);
    }

    public function test_ingest_skips_when_edited_by_another_user(): void
    {
        $this->seedOwner();
        $this->fakeTenraiAndLlm(spanish: true);
        app(CatalogNewsIngest::class)->run(1);

        $post = Post::query()->first();
        $post->title = 'Título editado a mano';
        $post->edited_by = 2;
        $post->save();

        app(CatalogNewsIngest::class)->run(1);

        $this->assertSame('Título editado a mano', $post->fresh()->title);
    }

    public function test_ingest_does_not_save_english(): void
    {
        $this->seedOwner();
        $this->fakeTenraiAndLlm(spanish: false);

        $result = app(CatalogNewsIngest::class)->run(1);

        $this->assertSame(0, Post::count());
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('skipped', $result['items'][0]['source']);
    }

    public function test_api_requires_auth_and_owner(): void
    {
        $this->seedOwner();
        $other = User::factory()->create();

        $this->postJson('/internal/news/ingest', ['limit' => 1])->assertUnauthorized();
        $this->actingAs($other, 'api')->postJson('/internal/news/ingest', ['limit' => 1])->assertForbidden();
        $this->actingAs(User::query()->find(1), 'api')->getJson('/internal/news/editorial')->assertOk();
    }

    public function test_api_ingest_and_editorial_status(): void
    {
        $this->seedOwner();
        $this->fakeTenraiAndLlm(spanish: true);
        $owner = User::query()->find(1);

        $this->actingAs($owner, 'api')
            ->postJson('/internal/news/ingest', ['limit' => 1])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('editorial.connected', true);

        $this->actingAs($owner, 'api')
            ->getJson('/internal/news/editorial')
            ->assertOk()
            ->assertJsonPath('editorial.configured', true);
    }

    private function seedOwner(): void
    {
        try {
            User::factory()->create(['id' => 1]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }
    }

    private function fakeTenraiAndLlm(bool $spanish): void
    {
        config([
            'services.editorial.key' => 'sk-test',
            'services.editorial.url' => 'https://api.openai.com/v1/chat/completions',
        ]);

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
        $llm = $spanish
            ? [
                'title' => '¡Phantom Busters revela su elenco!',
                'excerpt' => 'El anime ya tiene caras confirmadas.',
                'content' => '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>',
            ]
            : [
                'title' => "'Phantom Busters' Announces Main Cast, Staff",
                'excerpt' => 'The official website for Phantom Busters...',
                'content' => '<p>The official website announced the main cast.</p>',
            ];

        Http::fake(function (Request $request) use ($list, $detail, $llm) {
            if (str_contains($request->url(), 'chat/completions')) {
                return Http::response([
                    'choices' => [['message' => ['content' => json_encode($llm, JSON_UNESCAPED_UNICODE)]]],
                ], 200);
            }
            if (preg_match('#/news/(\d+)#', $request->url())) {
                return Http::response(['data' => $detail], 200);
            }

            return Http::response(['data' => $list], 200);
        });
    }
}
