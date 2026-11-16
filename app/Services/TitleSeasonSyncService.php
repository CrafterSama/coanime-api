<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HiddenSeeker;
use App\Models\Title;
use App\Support\TitleCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class TitleSeasonSyncService
{
    protected const DEFAULT_USER_ID = 1;

    protected const MAL_PLACEHOLDER_IMAGE = 'https://cdn.myanimelist.net/img/sp/icon/apple-touch-icon-256.png';

    /**
     * @param  array{year?: int, season?: string, page?: int}  $options
     * @return array{saved: int, skipped: int, invalid_type: int, errors: array<int, string>, processed: array<int, string>, lines: array<int, string>}
     */
    public function sync(array $options = []): array
    {
        $year = $options['year'] ?? (int) date('Y');
        $season = $options['season'] ?? $this->currentSeason();
        $page = isset($options['page']) ? (int) $options['page'] : 1;
        $page = $page >= 1 ? $page : 1;

        $out = [
            'saved' => 0,
            'skipped' => 0,
            'invalid_type' => 0,
            'errors' => [],
            'processed' => [],
            'lines' => [],
        ];

        try {
            $data = TitleCatalog::season($year, $season, $page);
        } catch (Throwable $e) {
            $out['errors'][] = 'Catalog error: '.$e->getMessage();

            return $out;
        }

        $out['lines'][] = "<p>Temporada {$season} {$year}</p>";

        foreach ($data as $cloudTitle) {
            $name = (string) ($cloudTitle['title'] ?? '');
            $slug = Str::slug($name);
            $out['processed'][] = "{$season} {$year}: {$name}";

            if (Title::where('slug', $slug)->exists()) {
                $out['skipped']++;
                $out['lines'][] = "<p>{$name} Ya existe</p>";
                continue;
            }

            $type = $cloudTitle['type'] ?? null;
            if ($type === null || $type === 'Unknown' || $type === 'Music') {
                $out['invalid_type']++;
                $out['lines'][] = "<p>{$name} No tiene determinado el Tipo</p>";
                continue;
            }

            $out['lines'][] = "<p>{$name} Procesando</p>";

            try {
                $this->createTitleFromCloud($cloudTitle);
                $out['saved']++;
                $out['lines'][] = "<p>{$name} Guardado</p>";
            } catch (Throwable $e) {
                $out['errors'][] = "{$name}: ".$e->getMessage();
            }
        }

        return $out;
    }

    public function currentSeason(): string
    {
        $m = (int) date('n');
        if ($m >= 3 && $m <= 5) {
            return 'spring';
        }
        if ($m >= 6 && $m <= 8) {
            return 'summer';
        }
        if ($m >= 9 && $m <= 11) {
            return 'fall';
        }

        return 'winter';
    }

    public function nextSeason(): string
    {
        $s = $this->currentSeason();

        return match ($s) {
            'winter' => 'spring',
            'spring' => 'summer',
            'summer' => 'fall',
            'fall' => 'winter',
            default => 'spring',
        };
    }

    public function nextSeasonYear(): int
    {
        $y = (int) date('Y');

        return $this->currentSeason() === 'fall' ? $y + 1 : $y;
    }

    protected function createTitleFromCloud(array $cloudTitle): Title
    {
        $title = new Title;
        $title->name = (string) ($cloudTitle['title'] ?? '');
        $title->slug = Str::slug($title->name);
        $title->user_id = self::DEFAULT_USER_ID;
        $title->just_year = 'false';
        $title->other_titles = $this->collectOtherTitles($cloudTitle);

        $synopsis = $cloudTitle['synopsis'] ?? null;
        if (is_string($synopsis) && $synopsis !== '') {
            try {
                $title->sinopsis = (new GoogleTranslate)->trans(
                    str_replace('[Written by MAL Rewrite]', '', $synopsis),
                    'es'
                );
            } catch (Throwable) {
                $title->sinopsis = $synopsis;
            }
        }

        $status = HiddenSeeker::getStatus(TitleCatalog::status($cloudTitle));
        $title->status = $status ?? 'Finalizado';

        $typeId = HiddenSeeker::getTypeById(strtolower((string) ($cloudTitle['type'] ?? '')));
        $title->type_id = $typeId;

        $rating = TitleCatalog::rating($cloudTitle);
        $title->rating_id = $rating === null ? 7 : (HiddenSeeker::getRatingId(strtolower($rating)) ?? 7);

        $title->episodies = TitleCatalog::episodes($cloudTitle, false) ?? 0;

        $from = TitleCatalog::dateFrom($cloudTitle);
        if ($from !== null) {
            $title->broad_time = Carbon::parse($from)->format('Y-m-d');
        }
        $to = TitleCatalog::dateTo($cloudTitle);
        if ($to !== null) {
            $title->broad_finish = Carbon::parse($to)->format('Y-m-d');
        }

        $title->save();

        if ($title->genres()->count() === 0) {
            $ids = $this->mapGenres($cloudTitle);
            if ($ids !== []) {
                $title->genres()->sync($ids);
            }
        }

        $this->attachCoverFromCloud($title, $cloudTitle);

        return $title;
    }

    protected function attachCoverFromCloud(Title $title, array $cloudTitle): void
    {
        $imageUrl = $this->getCoverImageUrlFromCloud($cloudTitle);
        if ($imageUrl === null || $imageUrl === '') {
            return;
        }

        try {
            $title->addMediaFromUrl($imageUrl)
                ->usingName("Title {$title->id} - {$title->name}")
                ->usingFileName('title-'.$title->id.'-cover.webp')
                ->toMediaCollection('cover');
        } catch (Throwable $e) {
            Log::warning('TitleSeasonSyncService: could not attach cover for title', [
                'title_id' => $title->id,
                'name' => $title->name,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getCoverImageUrlFromCloud(array $cloudTitle): ?string
    {
        $url = TitleCatalog::coverUrl($cloudTitle);
        if ($url === null || $url === '' || $url === self::MAL_PLACEHOLDER_IMAGE) {
            return null;
        }

        return $url;
    }

    private function collectOtherTitles(array $cloud): string
    {
        $other = [];
        foreach ($cloud['titles'] ?? [] as $t) {
            $type = strtolower((string) ($t['type'] ?? ''));
            $name = (string) ($t['title'] ?? '');
            if ($type === 'english' && $name !== '') {
                $s = $name.' (Inglés)';
                if (! in_array($s, $other, true)) {
                    $other[] = $s;
                }
            }
            if ($type === 'japanese' && $name !== '') {
                $s = $name.' (Japonés)';
                if (! in_array($s, $other, true)) {
                    $other[] = $s;
                }
            }
        }

        return implode(', ', $other);
    }

    private function mapGenres(array $cloud): array
    {
        $ids = [];
        foreach ($cloud['genres'] ?? [] as $g) {
            $name = is_array($g) ? (string) ($g['name'] ?? '') : (string) $g;
            if ($name === '') {
                continue;
            }
            $id = HiddenSeeker::getGenres(strtolower($name));
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
