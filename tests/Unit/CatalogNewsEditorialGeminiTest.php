<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CatalogNewsEditorial;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogNewsEditorialGeminiTest extends TestCase
{
    public function test_article_falls_back_when_html_links_dropped(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'title' => '¡Titular en español!',
                        'excerpt' => 'Bajada en español con datos.',
                        'content' => '<p>Texto sin el enlace original.</p>',
                    ], JSON_UNESCAPED_UNICODE)]]],
                ]],
            ], 200),
        ]);

        $row = CatalogNewsEditorial::article(
            1,
            'English title',
            'English excerpt',
            '<p>See <a href="https://example.com">source</a>.</p>'
        );

        $this->assertSame('translate', $row['source']);
        $this->assertStringContainsString('<a href="https://example.com">', $row['content']);
    }

    public function test_disabled_editorial_uses_translate_fallback(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => false,
        ]);

        Http::fake();

        $row = CatalogNewsEditorial::article(
            2,
            'English title here',
            'English excerpt here',
            '<p>English body.</p>'
        );

        $this->assertSame('translate', $row['source']);
        Http::assertNothingSent();
    }
}
