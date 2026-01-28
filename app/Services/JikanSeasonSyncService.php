<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HiddenSeeker;
use App\Models\Title;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Jikan\JikanPHP\Client;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class JikanSeasonSyncService
{
    protected const DEFAULT_USER_ID = 1;

    /**
     * Sync titles from Jikan MAL API by season.
     *
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
            $jikan = Client::create();
            $results = $jikan->getSeason($year, $season, compact('page'));
            $data = $results->getData();
        } catch (Throwable $e) {
            $out['errors'][] = 'Jikan API error: ' . $e->getMessage();

            return $out;
        }

        $out['lines'][] = "<p>Pagina {$page}</p>";

        foreach ($data as $cloudTitle) {
            $name = $cloudTitle->getTitle();
            $slug = Str::slug($name);
            $out['processed'][] = "Page {$page}: {$name}";

            if (Title::where('slug', $slug)->exists()) {
                $out['skipped']++;
                $out['lines'][] = "<p>{$name} Ya existe</p>";
                continue;
            }

            $type = $cloudTitle->getType();
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
                $out['errors'][] = "{$name}: " . $e->getMessage();
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

    /**
     * Next season (winter -> spring -> summer -> fall -> winter).
     */
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

    /**
     * Year for next season. Current year except when moving fall -> winter (next year).
     */
    public function nextSeasonYear(): int
    {
        $y = (int) date('Y');
        return $this->currentSeason() === 'fall' ? $y + 1 : $y;
    }

    protected function createTitleFromCloud(mixed $cloudTitle): Title
    {
        $title = new Title;
        $title->name = $cloudTitle->getTitle();
        $title->slug = Str::slug($title->name);
        $title->user_id = self::DEFAULT_USER_ID;
        $title->just_year = 'false';

        $titles = $cloudTitle->getTitles();
        if ($titles !== null) {
            $other = [];
            foreach ($titles as $t) {
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
            $title->other_titles = implode(', ', $other);
        }

        $synopsis = $cloudTitle->getSynopsis();
        if ($synopsis !== null && $synopsis !== '') {
            try {
                $title->sinopsis = (new GoogleTranslate)->trans(
                    str_replace('[Written by MAL Rewrite]', '', $synopsis),
                    'es'
                );
            } catch (Throwable) {
                $title->sinopsis = $synopsis;
            }
        }

        $trailer = $cloudTitle->getTrailer();
        if ($trailer !== null && method_exists($trailer, 'getUrl')) {
            $url = $trailer->getUrl();
            if ($url !== null && $url !== '') {
                $title->trailer_url = $url;
            }
        }

        $status = HiddenSeeker::getStatus($cloudTitle->getStatus());
        $title->status = $status ?? 'Finalizado';

        $typeId = HiddenSeeker::getTypeById(strtolower((string) $cloudTitle->getType()));
        $title->type_id = $typeId;

        $rating = $cloudTitle->getRating();
        $title->rating_id = $rating === null ? 7 : (HiddenSeeker::getRatingId(strtolower((string) $rating)) ?? 7);

        $episodes = $cloudTitle->getEpisodes();
        $title->episodies = $episodes ?? 0;

        $aired = $cloudTitle->getAired();
        if ($aired !== null) {
            $from = method_exists($aired, 'getFrom') ? $aired->getFrom() : null;
            if ($from !== null && $from !== '') {
                $title->broad_time = Carbon::parse($from)->format('Y-m-d');
            }
            $to = method_exists($aired, 'getTo') ? $aired->getTo() : null;
            if ($to !== null && $to !== '') {
                $title->broad_finish = Carbon::parse($to)->format('Y-m-d');
            }
        }

        $title->save();

        $genres = $cloudTitle->getGenres();
        if (is_iterable($genres) && $title->genres()->count() === 0) {
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
            if ($ids !== []) {
                $title->genres()->sync($ids);
            }
        }

        return $title;
    }
}
