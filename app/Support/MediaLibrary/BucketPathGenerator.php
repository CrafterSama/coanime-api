<?php

declare(strict_types=1);

namespace App\Support\MediaLibrary;

use App\Models\Company;
use App\Models\Event;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Path generator that uses the existing S3 bucket folder structure:
 * - posts/{Y/m}/{id}/
 * - titles/{type_slug}/{normalized_series_name}/{id}/
 * - magazine/{normalized_name}/{id}/
 * - people/{normalized_name}/{id}/
 * - companies/{normalized_name}/{id}/
 * - events/{Y/m}/{id}/
 * - users/{avatar|cover}/{id}/
 *
 * This allows all media to live under the correct context folder so that
 * legacy files outside these paths can be safely removed after migration.
 */
class BucketPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    /**
     * Base path (no trailing slash) for this media, relative to disk root.
     * Format: {context_folder}/{...subfolders}/{media_id}
     */
    protected function getBasePath(Media $media): string
    {
        $prefix = config('media-library.prefix', '');
        $prefix = $prefix !== '' ? rtrim($prefix, '/') . '/' : '';

        $contextPath = $this->getContextPath($media);
        $id = $media->getKey();

        return $prefix . $contextPath . '/' . $id;
    }

    /**
     * Context path segment (e.g. "posts/2025/02", "titles/manga/one-piece") without media id.
     */
    protected function getContextPath(Media $media): string
    {
        $model = $media->model_type ? $this->resolveModel($media) : null;

        if ($model === null) {
            return 'unknown/' . $media->getKey();
        }

        return match ($media->model_type) {
            Post::class => $this->pathForPost($model),
            Title::class => $this->pathForTitle($model),
            Magazine::class => $this->pathForMagazine($model),
            People::class => $this->pathForPeople($model),
            Company::class => $this->pathForCompany($model),
            Event::class => $this->pathForEvent($model),
            User::class => $this->pathForUser($media, $model),
            default => 'unknown/' . $media->getKey(),
        };
    }

    /** Posts: por fecha de creación del artículo. */
    protected function pathForPost(Post $post): string
    {
        $date = $post->created_at ?? now();

        return 'posts/' . $date->format('Y/m');
    }

    protected function pathForTitle(Title $title): string
    {
        $typeSlug = 'other';
        if ($title->relationLoaded('type') && $title->type) {
            $typeSlug = $this->normalizeSlug((string) $title->type->slug);
        } elseif ($title->type_id) {
            $type = $title->type()->first();
            $typeSlug = $type ? $this->normalizeSlug((string) $type->slug) : 'other';
        }
        $name = $this->normalizeName((string) ($title->name ?? 'unknown'));

        return 'titles/' . $typeSlug . '/' . $name;
    }

    protected function pathForMagazine(Magazine $magazine): string
    {
        $name = $this->normalizeName((string) ($magazine->name ?? $magazine->slug ?? 'unknown'));

        return 'magazine/' . $name;
    }

    protected function pathForPeople(People $person): string
    {
        $name = $this->normalizeName((string) ($person->name ?? 'unknown'));

        return 'people/' . $name;
    }

    protected function pathForCompany(Company $company): string
    {
        $name = $this->normalizeName((string) ($company->name ?? $company->slug ?? 'unknown'));

        return 'companies/' . $name;
    }

    /** Eventos: por fecha del evento (date_start), no por fecha de creación. */
    protected function pathForEvent(Event $event): string
    {
        $date = $event->date_start ?? $event->date_end ?? $event->created_at ?? now();

        return 'events/' . $date->format('Y/m');
    }

    protected function pathForUser(Media $media, User $user): string
    {
        $subFolder = $media->collection_name === 'cover' ? 'cover' : 'avatar';

        return 'users/' . $subFolder;
    }

    /**
     * Normalize a display name for use in paths: lowercase, ASCII only, spaces to dashes.
     * "Solo consonantes y vocales normales (anglosajonas sin acentos ni símbolos)".
     */
    public static function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'unknown';
        }
        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[^a-z0-9\s-]/u', '', $value);
        $value = (string) preg_replace('/\s+/', '-', $value);
        $value = (string) preg_replace('/-+/', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'unknown';
    }

    /**
     * Normalize a slug (e.g. type slug) for path: already short, just lowercase and safe chars.
     */
    protected function normalizeSlug(string $slug): string
    {
        $slug = Str::ascii(mb_strtolower(trim($slug)));
        $slug = (string) preg_replace('/[^a-z0-9_-]/', '', $slug);

        return $slug !== '' ? $slug : 'other';
    }

    protected function resolveModel(Media $media): ?object
    {
        $type = $media->model_type;
        $id = $media->model_id;

        if (!$type || $id === null) {
            return null;
        }

        if (!class_exists($type)) {
            return null;
        }

        try {
            return $type::withoutGlobalScopes()->find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
