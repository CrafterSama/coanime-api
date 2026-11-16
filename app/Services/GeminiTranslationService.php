<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeminiTranslationService
{
    private const MAX_ATTEMPTS = 3;

    private ?string $lastError = null;

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
        $model = config('services.editorial.model', 'gemini-3.8-flash');

        return is_string($model) && $model !== '' ? $model : 'gemini-3.8-flash';
    }

    public function host(): ?string
    {
        $base = config('services.editorial.base_url');

        return is_string($base) ? (parse_url($base, PHP_URL_HOST) ?: $base) : null;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array{data: array<string, mixed>, tokens_in: int|null, tokens_out: int|null}|null
     */
    public function generateJson(string $system, string $user, ?array $schema = null): ?array
    {
        $this->lastError = null;

        if (! $this->configured()) {
            $this->lastError = 'not configured';

            return null;
        }

        $key = (string) config('services.editorial.key');
        $base = rtrim((string) config('services.editorial.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = $base.'/models/'.$this->model().':generateContent';

        // First try with schema; if API rejects schema, retry JSON-only once.
        $result = $this->requestJson($url, $key, $system, $user, $schema);
        if ($result !== null) {
            return $result;
        }

        if ($schema !== null && $this->lastError !== null && str_contains(mb_strtolower($this->lastError), 'schema')) {
            return $this->requestJson($url, $key, $system, $user, null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array{data: array<string, mixed>, tokens_in: int|null, tokens_out: int|null}|null
     */
    private function requestJson(string $url, string $key, string $system, string $user, ?array $schema): ?array
    {
        $generationConfig = [
            'temperature' => 0.4,
            'responseMimeType' => 'application/json',
        ];
        if ($schema !== null) {
            $generationConfig['responseSchema'] = self::normalizeSchema($schema);
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
                // Official samples use ?key=; header alone fails on some hosts/proxies.
                $response = Http::withHeaders([
                    'x-goog-api-key' => $key,
                ])
                    ->timeout(45)
                    ->acceptJson()
                    ->asJson()
                    ->post($url.'?key='.urlencode($key), $payload);

                if ($response->status() === 429 || $response->serverError()) {
                    $this->lastError = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240);
                    $this->backoff($attempt);
                    if ($attempt >= self::MAX_ATTEMPTS) {
                        Log::warning('Gemini quota/HTTP error', [
                            'status' => $response->status(),
                            'body' => mb_substr($response->body(), 0, 300),
                            'model' => $this->model(),
                        ]);

                        return null;
                    }

                    continue;
                }

                if (! $response->successful()) {
                    $this->lastError = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240);
                    Log::warning('Gemini HTTP error', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 300),
                        'model' => $this->model(),
                        'url' => $url,
                    ]);

                    return null;
                }

                $block = $response->json('promptFeedback.blockReason');
                if (is_string($block) && $block !== '') {
                    $this->lastError = 'blocked: '.$block;

                    return null;
                }

                $finish = $response->json('candidates.0.finishReason');
                $text = $response->json('candidates.0.content.parts.0.text');
                if (! is_string($text) || trim($text) === '') {
                    $this->lastError = 'empty content'
                        .(is_string($finish) ? ' (finishReason='.$finish.')' : '')
                        .' body='.mb_substr($response->body(), 0, 180);

                    return null;
                }

                $decoded = json_decode(trim($text), true);
                if (! is_array($decoded)) {
                    $this->lastError = 'invalid json: '.mb_substr($text, 0, 120);

                    return null;
                }

                $usage = $response->json('usageMetadata');
                $this->lastError = null;

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
                $this->lastError = $e->getMessage();
                Log::warning('Gemini request failed', ['message' => $e->getMessage()]);
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return null;
                }
                $this->backoff($attempt);
            }
        }

        return null;
    }

    /**
     * Gemini accepts OpenAPI-ish schemas; prefer lowercase types from current docs.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function normalizeSchema(array $schema): array
    {
        $map = [
            'OBJECT' => 'object',
            'ARRAY' => 'array',
            'STRING' => 'string',
            'INTEGER' => 'integer',
            'NUMBER' => 'number',
            'BOOLEAN' => 'boolean',
        ];

        $out = [];
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $out[$key] = $map[strtoupper($value)] ?? strtolower($value);
            } elseif (is_array($value)) {
                $out[$key] = self::normalizeSchema($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function backoff(int $attempt): void
    {
        if (app()->environment('testing')) {
            return;
        }
        usleep(min(2_000_000, (int) (100_000 * (2 ** $attempt))));
    }
}
