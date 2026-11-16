<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeminiTranslationService
{
    private const MAX_ATTEMPTS = 3;

    public function configured(): bool
    {
        if (! (bool) config('services.editorial.enabled', true)) {
            return false;
        }
        $key = config('services.editorial.key');

        return is_string($key) && $key !== '';
    }

    public function model(): string
    {
        $model = config('services.editorial.model', 'gemini-2.5-flash');

        return is_string($model) && $model !== '' ? $model : 'gemini-2.5-flash';
    }

    public function host(): ?string
    {
        $base = config('services.editorial.base_url');

        return is_string($base) ? (parse_url($base, PHP_URL_HOST) ?: $base) : null;
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array{data: array<string, mixed>, tokens_in: int|null, tokens_out: int|null}|null
     */
    public function generateJson(string $system, string $user, ?array $schema = null): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $key = (string) config('services.editorial.key');
        $base = rtrim((string) config('services.editorial.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = $base.'/models/'.$this->model().':generateContent';

        $generationConfig = [
            'temperature' => 0.4,
            'responseMimeType' => 'application/json',
        ];
        if ($schema !== null) {
            $generationConfig['responseSchema'] = $schema;
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $user]],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        $attempt = 0;
        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            try {
                $response = Http::withHeaders([
                    'x-goog-api-key' => $key,
                ])
                    ->timeout(45)
                    ->acceptJson()
                    ->post($url, $payload);

                if ($response->status() === 429 || $response->serverError()) {
                    $this->backoff($attempt);
                    if ($attempt >= self::MAX_ATTEMPTS) {
                        Log::warning('Gemini quota/HTTP error', [
                            'status' => $response->status(),
                            'body' => mb_substr($response->body(), 0, 300),
                        ]);

                        return null;
                    }

                    continue;
                }

                if (! $response->successful()) {
                    Log::warning('Gemini HTTP error', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);

                    return null;
                }

                $text = $response->json('candidates.0.content.parts.0.text');
                if (! is_string($text) || trim($text) === '') {
                    return null;
                }

                $decoded = json_decode(trim($text), true);
                if (! is_array($decoded)) {
                    return null;
                }

                $usage = $response->json('usageMetadata');

                return [
                    'data' => $decoded,
                    'tokens_in' => is_array($usage) && isset($usage['promptTokenCount'])
                        ? (int) $usage['promptTokenCount']
                        : null,
                    'tokens_out' => is_array($usage) && isset($usage['candidatesTokenCount'])
                        ? (int) $usage['candidatesTokenCount']
                        : null,
                ];
            } catch (Throwable $e) {
                Log::warning('Gemini request failed', ['message' => $e->getMessage()]);
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return null;
                }
                $this->backoff($attempt);
            }
        }

        return null;
    }

    private function backoff(int $attempt): void
    {
        if (app()->environment('testing')) {
            return;
        }
        usleep(min(2_000_000, (int) (100_000 * (2 ** $attempt))));
    }
}
