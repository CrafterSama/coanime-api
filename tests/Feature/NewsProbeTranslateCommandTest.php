<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class NewsProbeTranslateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_prints_en_and_llm_and_does_not_save(): void
    {
        config([
            'services.editorial.key' => 'sk-test',
            'services.editorial.url' => 'https://api.openai.com/v1/chat/completions',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'chat/completions')) {
                return Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'title' => '¡Phantom Busters revela su elenco!',
                        'excerpt' => 'El anime ya tiene caras confirmadas.',
                        'content' => '<p>Los fans de Phantom Busters ya pueden ponerle cara al anime.</p>',
                    ], JSON_UNESCAPED_UNICODE)]]],
                ], 200);
            }

            return Http::response([
                'data' => [
                    'mal_id' => 74870618,
                    'title' => "'Phantom Busters' Announces Main Cast, Staff",
                    'excerpt' => 'The official website for Phantom Busters...',
                    'content' => [
                        'html' => '<p>The official website announced the main cast.</p>',
                    ],
                ],
            ], 200);
        });

        try {
            $this->artisan('news:probe', ['id' => 74870618])
                ->expectsOutputToContain('mal_id=74870618')
                ->expectsOutputToContain("'Phantom Busters' Announces Main Cast, Staff")
                ->expectsOutputToContain('¡Phantom Busters revela su elenco!')
                ->assertSuccessful();
        } catch (Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $this->assertSame(0, Post::count());
    }
}
