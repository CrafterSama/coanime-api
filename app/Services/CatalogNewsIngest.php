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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CatalogNewsIngest
{
    public const USER_ID = 1;

    /**
     * @return array{editorial: array<string, mixed>, created: int, updated: int, skipped: int, items: list<array<string, mixed>>}
     */
    public function run(int $limit = 10): array
    {
        if (User::query()->find(self::USER_ID) === null) {
            Log::error('news:ingest failed', ['exception' => 'User id 1 no existe']);
            throw new RuntimeException('User id 1 no existe');
        }

        $limit = max(1, min($limit, 25));
        Log::info('news:ingest start', ['limit' => $limit, 'editorial' => CatalogNewsEditorial::status()]);

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

            $date = is_string($detail['date'] ?? null) ? $detail['date'] : null;
            $postponed = $date !== null ? Carbon::parse($date) : Carbon::now();
            $postCreatedAt = $postponed->format('Y-m-d H:i:s');
            $image = $this->imageUrl($detail) ?? $this->imageUrl($raw);
            $payload = $this->payload($malId, $copy, $body, $image, $postponed, $postCreatedAt);

            if (! CatalogNewsEditorial::isSpanish($copy['title'], $copy['excerpt'], $body, $enTitle, $enExcerpt)) {
                $skipped++;
                $items[] = ['mal_id' => $malId, 'source' => CatalogNewsEditorial::SOURCE_SKIPPED, 'action' => 'skipped_not_spanish'];
                $this->logItem('warning', $malId, 'skipped_not_spanish', CatalogNewsEditorial::SOURCE_SKIPPED, $payload);

                continue;
            }

            $post = Post::query()->where('mal_news_id', $malId)->first();
            if ($post !== null && $post->edited_by !== null && (int) $post->edited_by !== self::USER_ID) {
                $skipped++;
                $items[] = ['mal_id' => $malId, 'source' => $copy['source'], 'action' => 'skipped_locked'];
                $this->logItem('warning', $malId, 'skipped_locked', $copy['source'], $payload, (int) $post->id);

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

            $post->title = $payload['title'];
            $post->excerpt = $payload['excerpt'];
            $post->content = $payload['content'];
            $post->image = $payload['image'];
            $post->postponed_to = $postponed;
            $post->post_created_at = $postCreatedAt;

            try {
                $post->save();
            } catch (Throwable $e) {
                $skipped++;
                $items[] = ['mal_id' => $malId, 'source' => $copy['source'], 'action' => 'save_failed'];
                $this->logItem('error', $malId, 'save_failed', $copy['source'], $payload, null, $e->getMessage());

                continue;
            }

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
            $this->logItem('info', $malId, $action, $copy['source'], $payload, (int) $post->id);
        }

        $editorial = CatalogNewsEditorial::status();
        Log::info('news:ingest done', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'editorial' => $editorial,
        ]);

        return [
            'editorial' => $editorial,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'items' => $items,
        ];
    }

    /**
     * @param  array{title: string, excerpt: string, content?: string, source?: string}  $copy
     * @return array<string, mixed>
     */
    private function payload(int $malId, array $copy, string $body, ?string $image, Carbon $postponed, string $postCreatedAt): array
    {
        return [
            'user_id' => self::USER_ID,
            'slug' => CatalogNews::slug($malId),
            'category_id' => CategoryEnum::CATEGORY_1->value,
            'title' => Str::limit($copy['title'], 255, ''),
            'excerpt' => Str::limit($copy['excerpt'], 255, ''),
            'content' => $body,
            'image' => $image,
            'postponed_to' => $postponed->format('Y-m-d H:i:s'),
            'post_created_at' => $postCreatedAt,
            'mal_news_id' => $malId,
            'approved' => PostApproved::YES->value,
            'draft' => PostDraft::PUBLISHED->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logItem(string $level, int $malId, string $action, string $source, array $payload, ?int $postId = null, ?string $exception = null): void
    {
        $context = [
            'mal_id' => $malId,
            'action' => $action,
            'source' => $source,
            'payload' => $payload,
        ];
        if ($postId !== null) {
            $context['post_id'] = $postId;
        }
        if ($exception !== null) {
            $context['exception'] = $exception;
        }
        Log::log($level, 'news:ingest item', $context);
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
