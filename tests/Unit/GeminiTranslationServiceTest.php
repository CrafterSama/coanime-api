<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeminiTranslationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiTranslationServiceTest extends TestCase
{
    public function test_generate_json_success(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
            'services.editorial.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"title":"ok"}']]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 11,
                    'candidatesTokenCount' => 3,
                ],
            ], 200),
        ]);

        $result = app(GeminiTranslationService::class)->generateJson('sys', 'user');

        $this->assertSame(['title' => 'ok'], $result['data']);
        $this->assertSame(11, $result['tokens_in']);
        $this->assertSame(3, $result['tokens_out']);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'gemini-2.5-flash:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && ($request['generationConfig']['responseMimeType'] ?? null) === 'application/json';
        });
    }

    public function test_429_returns_null_after_retries(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
        ]);

        $this->assertNull(app(GeminiTranslationService::class)->generateJson('sys', 'user'));
    }

    public function test_invalid_json_returns_null(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'not-json']]],
                ]],
            ], 200),
        ]);

        $this->assertNull(app(GeminiTranslationService::class)->generateJson('sys', 'user'));
    }

    public function test_disabled_or_missing_key(): void
    {
        config([
            'services.editorial.key' => 'test-key',
            'services.editorial.enabled' => false,
        ]);
        $this->assertFalse(app(GeminiTranslationService::class)->configured());

        config([
            'services.editorial.key' => '',
            'services.editorial.enabled' => true,
        ]);
        $this->assertFalse(app(GeminiTranslationService::class)->configured());
        $this->assertNull(app(GeminiTranslationService::class)->generateJson('sys', 'user'));
    }
}
