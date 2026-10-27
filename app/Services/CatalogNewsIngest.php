<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Category as CategoryEnum;
use App\Enums\PostApproved;
use App\Enums\PostDraft;
use App\Models\Post;
use App\Models\User;
use App\Support\CatalogNews;
use App\Support\CatalogNewsEditorial;
use App\Support\TitleCatalog;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

final class CatalogNewsIngest
{
    public const USER_ID = 1;

    /**
     * @return array{editorial: array<string, mixed>, created: int, updated: int, skipped: int, items: list<array<string, mixed>>}
     */
    public function run(int $limit = 10): array
    {
        if (User::query()->find(self::USER_ID) === null) {
            throw new RuntimeException('User id 1 no existe');
        }

        $limit = max(1, min($limit, 25));
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $items = [];

        foreach (TitleCatalog::news($limit) as $raw) {
            if (! is_array($raw) || ! isset($raw['mal_id'])) {
                continue;
            }
            $malId = (int) $raw['mal_id'];
            $detail = TitleCatalog::newsById($malId) ?? $raw;
            $html = '';
            $content = $detail['content'] ?? null;
            if (is_array($content) && is_string($content['html'] ?? null)) {
                $html = $content['html'];
            }

            $enTitle = (string) ($detail['title'] ?? $raw['title'] ?? '');
            $enExcerpt = (string) ($detail['excerpt'] ?? $raw['excerpt'] ?? '');
            $copy = CatalogNewsEditorial::article($malId, $enTitle, $enExcerpt, $html);
            $body = $copy['content'];
            if (! str_contains($body, 'Fuente: MyAnimeList')) {
                $body = rtrim($body).CatalogNews::ATTRIBUTION;
            }

            if (! CatalogNewsEditorial::isSpanish($copy['title'], $copy['excerpt'], $body, $enTitle, $enExcerpt)) {
                $skipped++;
                $items[] = ['mal_id' => $malId, 'source' => CatalogNewsEditorial::SOURCE_SKIPPED, 'action' => 'skipped_not_spanish'];

                continue;
            }

            $post = Post::query()->where('mal_news_id', $malId)->first();
            if ($post !== null && $post->edited_by !== null && (int) $post->edited_by !== self::USER_ID) {
                $skipped++;
                $items[] = ['mal_id' => $malId, 'source' => $copy['source'], 'action' => 'skipped_locked'];

                continue;
            }

            $isNew = $post === null;
            if ($isNew) {
                $post = new Post;
                $post->mal_news_id = $malId;
                $post->user_id = self::USER_ID;
                $post->slug = CatalogNews::slug($malId);
                $post->category_id = CategoryEnum::CATEGORY_1->value;
                $post->approved = PostApproved::YES;
                $post->draft = PostDraft::PUBLISHED;
            }

            $date = is_string($detail['date'] ?? null) ? $detail['date'] : null;
            $post->title = Str::limit($copy['title'], 255, '');
            $post->excerpt = Str::limit($copy['excerpt'], 255, '');
            $post->content = $body;
            $post->image = $this->imageUrl($detail) ?? $this->imageUrl($raw);
            $post->postponed_to = $date !== null ? Carbon::parse($date) : Carbon::now();
            $post->post_created_at = $date !== null ? Carbon::parse($date)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
            $post->save();

            if ($isNew) {
                $created++;
                $action = 'created';
            } else {
                $updated++;
                $action = 'updated';
            }
            $items[] = [
                'mal_id' => $malId,
                'post_id' => $post->id,
                'source' => $copy['source'],
                'action' => $action,
            ];
        }

        return [
            'editorial' => CatalogNewsEditorial::status(),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function imageUrl(array $item): ?string
    {
        $url = $item['images']['jpg']['image_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
