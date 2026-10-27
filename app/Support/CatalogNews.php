<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Category as CategoryEnum;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class CatalogNews
{
    public const SLUG_PREFIX = 'mal-news-';
    private const TTL = 1800;
    public const ATTRIBUTION = '<p>Fuente: MyAnimeList (Tenrai)</p>';

    public static function slug(int $malId): string
    {
        return self::SLUG_PREFIX.$malId;
    }

    public static function parseSlug(string $slug): ?int
    {
        if (preg_match('/^'.preg_quote(self::SLUG_PREFIX, '/').'(\d+)$/', $slug, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * @param  iterable<int, mixed>  $local
     * @return list<mixed>
     */
    public static function fill(iterable $local, int $limit = 3): array
    {
        $items = [];
        $seen = [];
        foreach (self::savedRelevants($limit) as $row) {
            self::pushUnique($items, $seen, $row, is_object($row) ? (string) $row->slug : '');
            if (count($items) >= $limit) {
                return $items;
            }
        }
        foreach ($local as $row) {
            $slug = is_object($row) ? (string) ($row->slug ?? '') : (string) ($row['slug'] ?? '');
            self::pushUnique($items, $seen, $row, $slug);
            if (count($items) >= $limit) {
                return $items;
            }
        }
        try {
            $extra = self::relevants($limit);
        } catch (Throwable) {
            $extra = [];
        }
        foreach ($extra as $row) {
            self::pushUnique($items, $seen, $row, (string) ($row['slug'] ?? ''));
            if (count($items) >= $limit) {
                break;
            }
        }

        return array_values(array_slice($items, 0, $limit));
    }

    /**
     * @return list<Post>
     */
    private static function savedRelevants(int $limit): array
    {
        try {
            return Post::query()
                ->select('id', 'title', 'excerpt', 'slug', 'category_id', 'image', 'view_counter', 'user_id', 'postponed_to', 'created_at', 'updated_at', 'approved', 'draft', 'post_created_at', 'mal_news_id')
                ->with('categories', 'tags', 'users')
                ->whereNotNull('mal_news_id')
                ->publishedAndApproved()
                ->where(function ($q) {
                    $q->where('postponed_to', '<=', now())->orWhereNull('postponed_to');
                })
                ->orderByDesc('postponed_to')
                ->orderByDesc('id')
                ->take($limit)
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, true>  $seen
     */
    private static function pushUnique(array &$items, array &$seen, mixed $row, string $slug): void
    {
        if ($slug !== '' && isset($seen[$slug])) {
            return;
        }
        if ($slug !== '') {
            $seen[$slug] = true;
        }
        $items[] = $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function relevants(int $limit): array
    {
        $limit = max(1, $limit);

        return Cache::remember('catalog.news.relevants.'.$limit, self::TTL, function () use ($limit) {
            $category = self::category();
            $raw = [];
            foreach (TitleCatalog::news($limit) as $item) {
                if (! is_array($item) || ! isset($item['mal_id'])) {
                    continue;
                }
                $raw[] = $item;
                if (count($raw) >= $limit) {
                    break;
                }
            }
            $copy = CatalogNewsEditorial::cards($raw);
            $mapped = [];
            foreach ($raw as $item) {
                $mapped[] = self::asRelevant($item, $category, $copy[(int) $item['mal_id']] ?? null);
            }

            return $mapped;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function article(int $malId): ?array
    {
        return Cache::remember('catalog.news.article.'.$malId, self::TTL, function () use ($malId) {
            $item = TitleCatalog::newsById($malId);
            if ($item === null) {
                return null;
            }

            return self::asArticle($item, self::category());
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{id: int, name: string, slug: string}  $category
     * @param  array{title: string, excerpt: string}|null  $copy
     * @return array<string, mixed>
     */
    public static function asRelevant(array $item, array $category, ?array $copy = null): array
    {
        $malId = (int) ($item['mal_id'] ?? 0);
        $date = is_string($item['date'] ?? null) ? $item['date'] : null;
        $copy ??= CatalogNewsEditorial::card(
            $malId,
            (string) ($item['title'] ?? ''),
            (string) ($item['excerpt'] ?? '')
        );

        return [
            'id' => $malId,
            'title' => $copy['title'],
            'excerpt' => $copy['excerpt'],
            'slug' => self::slug($malId),
            'category_id' => $category['id'],
            'image' => self::imageUrl($item),
            'view_counter' => is_numeric($item['comments'] ?? null) ? (int) $item['comments'] : 0,
            'user_id' => 0,
            'postponed_to' => $date,
            'created_at' => $date,
            'updated_at' => $date,
            'approved' => 'yes',
            'draft' => '0',
            'post_created_at' => $date,
            'categories' => $category,
            'tags' => [],
            'users' => [
                'name' => CatalogNewsEditorial::AUTHOR,
                'slug' => Str::slug(CatalogNewsEditorial::AUTHOR),
            ],
            'source' => 'tenrai',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{id: int, name: string, slug: string}  $category
     * @return array<string, mixed>
     */
    public static function asArticle(array $item, array $category): array
    {
        $malId = (int) ($item['mal_id'] ?? 0);
        $html = '';
        $content = $item['content'] ?? null;
        if (is_array($content) && is_string($content['html'] ?? null)) {
            $html = $content['html'];
        }
        $copy = CatalogNewsEditorial::article(
            $malId,
            (string) ($item['title'] ?? ''),
            (string) ($item['excerpt'] ?? ''),
            $html
        );
        $post = self::asRelevant($item, $category, $copy);
        $body = $copy['content'];
        if (! str_contains($body, 'Fuente: MyAnimeList')) {
            $body = rtrim($body).self::ATTRIBUTION;
        }
        $post['content'] = $body;
        $post['titles'] = [];

        return $post;
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private static function category(): array
    {
        $id = CategoryEnum::CATEGORY_1->value;
        try {
            $cat = Category::query()->select(['id', 'name', 'slug'])->find($id);
            if ($cat !== null) {
                return [
                    'id' => (int) $cat->id,
                    'name' => (string) $cat->name,
                    'slug' => (string) $cat->slug,
                ];
            }
        } catch (Throwable) {
        }

        return ['id' => $id, 'name' => 'Noticias', 'slug' => 'noticias'];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function imageUrl(array $item): ?string
    {
        $url = $item['images']['jpg']['image_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
