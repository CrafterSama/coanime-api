<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HiddenSeeker;
use App\Models\Title;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Jikan\JikanPHP\Client;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class MalTitleEnrichmentService
{
    private const MAL_PLACEHOLDER_IMAGE = 'https://cdn.myanimelist.net/img/sp/icon/apple-touch-icon-256.png';

    private const SYNOPSIS_PLACEHOLDERS = [
        'Sinopsis no disponible',
        'Sinopsis no disponible.',
        'Pendiente de agregar sinopsis...',
        'Sinopsis en Proceso',
    ];

    /**
     * Enrich title from MAL via Jikan (search by name + type). Updates only missing fields.
     * Call inside Title::withoutEvents(...) to avoid observer loop.
     */
    public function enrich(Title $title): bool
    {
        $title->loadMissing('type');
        if ($title->type === null || $title->type->slug === null) {
            return false;
        }

        $slug = strtolower((string) $title->type->slug);
        $malType = HiddenSeeker::getType($slug);
        if ($malType === null) {
            return false;
        }

        $isManga = in_array($malType, [
            'manga', 'manhwa', 'manhua', 'novel', 'one-shot', 'doujinshi', 'light novel',
        ], true);

        try {
            $jikan = Client::create();
            $results = $isManga
                ? $jikan->getMangaSearch(['q' => $title->name, 'type' => $malType])
                : $jikan->getAnimeSearch(['q' => $title->name, 'type' => $malType]);
            $data = $results->getData();
        } catch (Throwable $e) {
            Log::warning('MalTitleEnrichmentService: Jikan search failed', [
                'title_id' => $title->id,
                'name' => $title->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $cloud = $this->pickBestMatch($data, $title->name, $malType);
        if ($cloud === null) {
            return false;
        }

        $updated = false;

        if ($this->missingSynopsis($title)) {
            $syn = $cloud->getSynopsis();
            if ($syn !== null && $syn !== '') {
                try {
                    $title->sinopsis = (new GoogleTranslate)->trans(
                        str_replace('[Written by MAL Rewrite]', '', $syn),
                        'es'
                    );
                    $updated = true;
                } catch (Throwable) {
                    $title->sinopsis = $syn;
                    $updated = true;
                }
            }
        }

        if (($title->trailer_url === null || $title->trailer_url === '') && ! $isManga) {
            $trailer = $cloud->getTrailer();
            if ($trailer !== null && method_exists($trailer, 'getUrl')) {
                $url = $trailer->getUrl();
                if ($url !== null && $url !== '') {
                    $title->trailer_url = $url;
                    $updated = true;
                }
            }
        }

        if ($title->rating_id === null || (int) $title->rating_id === 7) {
            $r = $cloud->getRating();
            $rid = $r === null ? 7 : (HiddenSeeker::getRatingId(strtolower((string) $r)) ?? 7);
            if ($rid !== null && (int) $rid !== 7) {
                $title->rating_id = $rid;
                $updated = true;
            }
        }

        if ($title->episodies === null || (int) $title->episodies === 0) {
            $eps = $isManga ? $cloud->getChapters() : $cloud->getEpisodes();
            if ($eps !== null) {
                $title->episodies = $eps;
                $updated = true;
            }
        }

        $zero = '0000-00-00 00:00:00';
        if ($title->broad_time === null || $title->broad_time === $zero) {
            $from = $isManga ? $this->publishedFrom($cloud) : $this->airedFrom($cloud);
            if ($from !== null) {
                $title->broad_time = Carbon::parse($from)->format('Y-m-d');
                $updated = true;
            }
        }
        if ($title->broad_finish === null || $title->broad_finish === $zero) {
            $to = $isManga ? $this->publishedTo($cloud) : $this->airedTo($cloud);
            if ($to !== null) {
                $title->broad_finish = Carbon::parse($to)->format('Y-m-d');
                $updated = true;
            }
        }

        $st = HiddenSeeker::getStatus($cloud->getStatus());
        if ($st !== null) {
            $title->status = $st;
            $updated = true;
        }

        if (($title->other_titles === null || trim((string) $title->other_titles) === '')) {
            $other = $this->collectOtherTitles($cloud);
            if ($other !== '') {
                $title->other_titles = $other;
                $updated = true;
            }
        }

        if ($title->genres()->count() === 0) {
            $ids = $this->mapGenres($cloud);
            if ($ids !== []) {
                $title->genres()->sync($ids);
                $updated = true;
            }
        }

        if ($title->getFirstMedia('cover') === null) {
            $this->attachCover($title, $cloud);
            $updated = true;
        }

        if ($updated) {
            $title->save();
        }

        return $updated;
    }

    private function pickBestMatch(array $data, string $name, string $malType): mixed
    {
        $nameLower = strtolower($name);
        $firstByType = null;
        foreach ($data as $item) {
            $t = $item->getType();
            if ($t === null || strtolower((string) $t) !== $malType) {
                continue;
            }
            if ($firstByType === null) {
                $firstByType = $item;
            }
            $itemTitle = $item->getTitle();
            if ($itemTitle !== null && strtolower($itemTitle) === $nameLower) {
                return $item;
            }
        }
        return $firstByType;
    }

    private function missingSynopsis(Title $title): bool
    {
        $s = trim((string) ($title->sinopsis ?? ''));
        if ($s === '') {
            return true;
        }
        return in_array($s, self::SYNOPSIS_PLACEHOLDERS, true);
    }

    private function airedFrom(mixed $cloud): ?string
    {
        $a = $cloud->getAired();
        if ($a === null) {
            return null;
        }
        return method_exists($a, 'getFrom') ? $a->getFrom() : null;
    }

    private function airedTo(mixed $cloud): ?string
    {
        $a = $cloud->getAired();
        if ($a === null) {
            return null;
        }
        return method_exists($a, 'getTo') ? $a->getTo() : null;
    }

    private function publishedFrom(mixed $cloud): ?string
    {
        $p = $cloud->getPublished();
        if ($p === null) {
            return null;
        }
        return method_exists($p, 'getFrom') ? $p->getFrom() : null;
    }

    private function publishedTo(mixed $cloud): ?string
    {
        $p = $cloud->getPublished();
        if ($p === null) {
            return null;
        }
        return method_exists($p, 'getTo') ? $p->getTo() : null;
    }

    private function collectOtherTitles(mixed $cloud): string
    {
        $list = $cloud->getTitles();
        if ($list === null || ! is_iterable($list)) {
            return '';
        }
        $other = [];
        foreach ($list as $t) {
            $type = strtolower((string) $t->getType());
            $name = $t->getTitle();
            if ($type === 'english' && $name !== '') {
                $s = $name . ' (Inglés)';
                if (! in_array($s, $other, true)) {
                    $other[] = $s;
                }
            }
            if ($type === 'japanese' && $name !== '') {
                $s = $name . ' (Japonés)';
                if (! in_array($s, $other, true)) {
                    $other[] = $s;
                }
            }
        }
        return implode(', ', $other);
    }

    private function mapGenres(mixed $cloud): array
    {
        $genres = $cloud->getGenres();
        if (! is_iterable($genres)) {
            return [];
        }
        $ids = [];
        foreach ($genres as $g) {
            if ($g === '' || $g === null) {
                continue;
            }
            $name = method_exists($g, 'getName') ? $g->getName() : (string) $g;
            $id = HiddenSeeker::getGenres(strtolower($name));
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function getCoverUrl(mixed $cloud): ?string
    {
        $images = $cloud->getImages();
        if ($images === null) {
            return null;
        }
        $webp = method_exists($images, 'getWebp') ? $images->getWebp() : null;
        if ($webp === null) {
            return null;
        }
        $url = method_exists($webp, 'getLargeImageUrl') ? $webp->getLargeImageUrl() : null;
        if ($url === null || $url === '' || $url === self::MAL_PLACEHOLDER_IMAGE) {
            return null;
        }
        return $url;
    }

    private function attachCover(Title $title, mixed $cloud): void
    {
        $url = $this->getCoverUrl($cloud);
        if ($url === null || $url === '') {
            return;
        }
        try {
            $title->addMediaFromUrl($url)
                ->usingName("Title {$title->id} - {$title->name}")
                ->usingFileName('title-' . $title->id . '-cover.webp')
                ->toMediaCollection('cover');
        } catch (Throwable $e) {
            Log::warning('MalTitleEnrichmentService: could not attach cover', [
                'title_id' => $title->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
