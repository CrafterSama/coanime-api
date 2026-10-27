<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

final class CatalogNewsEditorial
{
    public const AUTHOR = 'Redacción Coanime';
    private const TTL = 86400;
    private const SYSTEM = <<<'PROMPT'
Eres la redacción de Coanime.net (tono hasta octubre 2024). Español de Latinoamérica.
Titular corto, a menudo con ¡!; bajada de una línea; lead que habla a los fans, no cable de agencia.
Subtítulos en <h4>. Conserva nombres de obras. No inventes fechas, staff ni reparto.
HTML simple: <p> y <h4>. Responde SOLO JSON válido.
PROMPT;

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, array{title: string, excerpt: string}>
     */
    public static function cards(array $items): array
    {
        $out = [];
        $miss = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['mal_id'])) {
                continue;
            }
            $id = (int) $item['mal_id'];
            $cached = Cache::get(self::cardKey($id));
            if (is_array($cached) && isset($cached['title'], $cached['excerpt'])) {
                $out[$id] = ['title' => (string) $cached['title'], 'excerpt' => (string) $cached['excerpt']];
            } else {
                $miss[] = $item;
            }
        }

        if ($miss === []) {
            return $out;
        }

        $batch = self::llmCards($miss);
        foreach ($miss as $item) {
            $id = (int) $item['mal_id'];
            $row = $batch[$id] ?? self::fallbackCard($item);
            Cache::put(self::cardKey($id), $row, self::TTL);
            $out[$id] = $row;
        }

        return $out;
    }

    /**
     * @return array{title: string, excerpt: string}
     */
    public static function card(int $malId, string $title, string $excerpt): array
    {
        $cards = self::cards([['mal_id' => $malId, 'title' => $title, 'excerpt' => $excerpt]]);

        return $cards[$malId] ?? self::fallbackCard(['mal_id' => $malId, 'title' => $title, 'excerpt' => $excerpt]);
    }

    /**
     * @return array{title: string, excerpt: string, content: string}
     */
    public static function article(int $malId, string $title, string $excerpt, string $html): array
    {
        $cached = Cache::get(self::articleKey($malId));
        if (is_array($cached) && isset($cached['title'], $cached['excerpt'], $cached['content'])) {
            return [
                'title' => (string) $cached['title'],
                'excerpt' => (string) $cached['excerpt'],
                'content' => (string) $cached['content'],
            ];
        }

        $row = self::llmArticle($malId, $title, $excerpt, $html)
            ?? self::fallbackArticle($malId, $title, $excerpt, $html);
        Cache::put(self::articleKey($malId), $row, self::TTL);

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, array{title: string, excerpt: string}>|null
     */
    private static function llmCards(array $items): ?array
    {
        $payload = [];
        foreach ($items as $item) {
            $payload[] = [
                'mal_id' => (int) $item['mal_id'],
                'title' => (string) ($item['title'] ?? ''),
                'excerpt' => (string) ($item['excerpt'] ?? ''),
            ];
        }

        $json = self::chat(
            'Reescribe estas fichas. JSON: {"items":[{"mal_id":0,"title":"","excerpt":""}]}'
            ."\n".json_encode(['items' => $payload], JSON_UNESCAPED_UNICODE)
        );
        if ($json === null || ! isset($json['items']) || ! is_array($json['items'])) {
            return null;
        }

        $out = [];
        foreach ($json['items'] as $row) {
            if (! is_array($row) || ! isset($row['mal_id'])) {
                continue;
            }
            $id = (int) $row['mal_id'];
            $title = trim((string) ($row['title'] ?? ''));
            $excerpt = trim((string) ($row['excerpt'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[$id] = ['title' => $title, 'excerpt' => $excerpt];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @return array{title: string, excerpt: string, content: string}|null
     */
    private static function llmArticle(int $malId, string $title, string $excerpt, string $html): ?array
    {
        $json = self::chat(
            'Reescribe esta nota completa. JSON: {"title":"","excerpt":"","content":""}.'
            .' content en HTML (<p>, <h4>). Sin inventar datos.'
            ."\n".json_encode([
                'mal_id' => $malId,
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => $html,
            ], JSON_UNESCAPED_UNICODE)
        );
        if ($json === null) {
            return null;
        }
        $outTitle = trim((string) ($json['title'] ?? ''));
        $outExcerpt = trim((string) ($json['excerpt'] ?? ''));
        $outContent = trim((string) ($json['content'] ?? ''));
        if ($outTitle === '' || $outContent === '') {
            return null;
        }

        return ['title' => $outTitle, 'excerpt' => $outExcerpt, 'content' => $outContent];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function chat(string $user): ?array
    {
        $key = config('services.editorial.key');
        $url = config('services.editorial.url');
        $model = config('services.editorial.model', 'gpt-4o-mini');
        if (! is_string($key) || $key === '' || ! is_string($url) || $url === '') {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(30)
                ->acceptJson()
                ->post($url, [
                    'model' => is_string($model) && $model !== '' ? $model : 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
            if (! $response->successful()) {
                return null;
            }
            $text = $response->json('choices.0.message.content');
            if (! is_string($text) || trim($text) === '') {
                return null;
            }
            $text = trim($text);
            $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, excerpt: string}
     */
    private static function fallbackCard(array $item): array
    {
        $id = (int) ($item['mal_id'] ?? 0);

        return [
            'title' => self::trans($id.'.title', (string) ($item['title'] ?? '')),
            'excerpt' => self::trans($id.'.excerpt', (string) ($item['excerpt'] ?? '')),
        ];
    }

    /**
     * @return array{title: string, excerpt: string, content: string}
     */
    private static function fallbackArticle(int $malId, string $title, string $excerpt, string $html): array
    {
        $card = self::fallbackCard(['mal_id' => $malId, 'title' => $title, 'excerpt' => $excerpt]);

        return [
            'title' => $card['title'],
            'excerpt' => $card['excerpt'],
            'content' => self::trans($malId.'.content', $html),
        ];
    }

    private static function trans(string $key, string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        return Cache::remember('catalog.news.i18n.'.$key, 1800, function () use ($text) {
            if (app()->environment('testing')) {
                return $text;
            }
            try {
                return (new GoogleTranslate)->trans($text, 'es');
            } catch (Throwable) {
                return $text;
            }
        });
    }

    private static function cardKey(int $malId): string
    {
        return 'catalog.news.editorial.card.'.$malId;
    }

    private static function articleKey(int $malId): string
    {
        return 'catalog.news.editorial.article.'.$malId;
    }
}
