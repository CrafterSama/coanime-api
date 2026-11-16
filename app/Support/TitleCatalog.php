<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class TitleCatalog
{
    public static function search(string $q, ?string $type = null, int $page = 1, ?string $letter = null, bool $manga = false): array
    {
        return self::searchPage($q, $type, $page, $letter, $manga)['data'];
    }

    /**
     * @return array{data: list<array<string, mixed>>, last_visible_page: int}
     */
    public static function searchPage(string $q, ?string $type = null, int $page = 1, ?string $letter = null, bool $manga = false): array
    {
        if (strlen($q) > 0 && strlen($q) < 3) {
            $letter = $letter ?: $q;
            $q = '';
        }

        $query = ['page' => max(1, $page), 'limit' => 25];
        if (strlen($q) >= 3) {
            $query['q'] = $q;
        } elseif ($letter !== null && $letter !== '') {
            $query['letter'] = substr($letter, 0, 1);
        }

        $mapped = self::catalogType($type, $manga);
        if ($mapped !== null) {
            $query['type'] = $mapped;
        }

        $json = self::get($manga ? '/manga' : '/anime', $query);

        return [
            'data' => $json['data'] ?? [],
            'last_visible_page' => (int) ($json['pagination']['last_visible_page'] ?? 1),
        ];
    }

    public static function season(int $year, string $season, int $page = 1): array
    {
        $json = self::get('/seasons/'.$year.'/'.strtolower($season), [
            'page' => max(1, $page),
            'limit' => 100,
        ]);

        return $json['data'] ?? [];
    }

    public static function schedule(string $day): array
    {
        $filter = self::dayFilter($day);
        if ($filter === null) {
            return [];
        }

        $json = self::get('/schedules', [
            'filter' => $filter,
            'limit' => 100,
        ]);

        return $json['data'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function news(int $limit = 3): array
    {
        $json = self::get('/news', [
            'limit' => max(1, min($limit, 100)),
            'sfw' => 'true',
        ]);
        $data = $json['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function newsById(int $id): ?array
    {
        try {
            $json = self::get('/news/'.$id, ['sfw' => 'true']);
        } catch (RequestException $e) {
            if ($e->response !== null && $e->response->status() === 404) {
                return null;
            }
            throw $e;
        }

        $data = $json['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    public static function dayFilter(string $day): ?string
    {
        return match (strtolower($day)) {
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' => strtolower($day),
            default => null,
        };
    }

    public static function dateFrom(array $item): ?string
    {
        return self::dateField($item, 'from');
    }

    public static function dateTo(array $item): ?string
    {
        return self::dateField($item, 'to');
    }

    public static function rating(array $item): ?string
    {
        $rating = $item['rating'] ?? null;

        return is_string($rating) && $rating !== '' ? $rating : null;
    }

    public static function coverUrl(array $item): ?string
    {
        $images = $item['images'] ?? [];
        $webp = is_array($images) ? ($images['webp']['large_image_url'] ?? null) : null;
        $jpg = is_array($images) ? ($images['jpg']['large_image_url'] ?? null) : null;
        $url = is_string($webp) && $webp !== '' ? $webp : $jpg;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function status(array $item): ?string
    {
        $status = $item['status'] ?? null;

        return is_string($status) && $status !== '' ? $status : null;
    }

    public static function episodes(array $item, bool $manga): ?int
    {
        $value = $manga ? ($item['chapters'] ?? null) : ($item['episodes'] ?? null);

        return is_numeric($value) ? (int) $value : null;
    }

    private static function dateField(array $item, string $which): ?string
    {
        $block = $item['aired'] ?? $item['published'] ?? null;
        $raw = is_array($block) ? ($block[$which] ?? null) : null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return substr($raw, 0, 10);
    }

    private static function catalogType(?string $type, bool $manga): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $key = strtolower($type);
        $map = $manga ? [
            'manga' => 'manga',
            'novel' => 'novel',
            'light novel' => 'lightnovel',
            'one-shot' => 'oneshot',
            'doujinshi' => 'doujin',
            'manhwa' => 'manhwa',
            'manhua' => 'manhua',
        ] : [
            'tv' => 'tv',
            'movie' => 'movie',
            'ova' => 'ova',
            'special' => 'special',
            'tv special' => 'tv_special',
            'ona' => 'ona',
            'music' => 'music',
        ];

        return $map[$key] ?? $key;
    }

    private static function get(string $path, array $query = []): array
    {
        $base = rtrim((string) config('services.catalog.url', 'https://api.tenrai.org/v1'), '/');
        $request = self::http();
        $response = $request->get($base.$path, $query);

        if ($response->status() === 429) {
            $wait = (int) $response->header('Retry-After', 1);
            sleep(max(1, min($wait, 10)));
            $response = $request->get($base.$path, $query);
        }

        $response->throw();

        return $response->json() ?? [];
    }

    private static function http(): PendingRequest
    {
        $request = Http::timeout(15)->acceptJson();
        $key = config('services.catalog.key');
        if (is_string($key) && $key !== '') {
            $request = $request->withHeaders(['X-Server-Key' => $key]);
        }

        return $request;
    }
}
