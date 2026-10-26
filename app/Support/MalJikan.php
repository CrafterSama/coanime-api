<?php

declare(strict_types=1);

namespace App\Support;

use Jikan\Model\Search\AnimeSearch;
use Jikan\Model\Search\MangaSearch;
use Jikan\MyAnimeList\MalClient;
use Jikan\Request\Schedule\ScheduleRequest;
use Jikan\Request\Search\AnimeSearchRequest;
use Jikan\Request\Search\MangaSearchRequest;
use Jikan\Request\Seasonal\SeasonalRequest;

final class MalJikan
{
    private const ANIME_TYPES = [
        'tv' => '1',
        'ova' => '2',
        'movie' => '3',
        'special' => '4',
        'tv special' => '4',
        'ona' => '5',
        'music' => '6',
    ];

    private const MANGA_TYPES = [
        'manga' => '1',
        'novel' => '2',
        'one-shot' => '3',
        'doujinshi' => '4',
        'manhwa' => '5',
        'manhua' => '6',
        'light novel' => '7',
    ];

    public static function client(): MalClient
    {
        return new MalClient();
    }

    public static function search(string $q, ?string $type = null, int $page = 1, ?string $letter = null, bool $manga = false): array
    {
        return self::searchResult($q, $type, $page, $letter, $manga)->getResults();
    }

    public static function searchResult(string $q, ?string $type = null, int $page = 1, ?string $letter = null, bool $manga = false): AnimeSearch|MangaSearch
    {
        if (strlen($q) > 0 && strlen($q) < 3) {
            $letter = $letter ?: $q;
            $q = '';
        }

        $query = strlen($q) >= 3 ? $q : null;
        $req = $manga
            ? new MangaSearchRequest($query, $page)
            : new AnimeSearchRequest($query, $page);

        $mapped = self::malType($type, $manga);
        if ($mapped !== null) {
            $req->setType($mapped);
        }
        if ($letter !== null && $letter !== '') {
            $req->setChar($letter);
        }

        return $manga
            ? self::client()->getMangaSearch($req)
            : self::client()->getAnimeSearch($req);
    }

    public static function season(int $year, string $season): array
    {
        return self::client()->getSeasonal(new SeasonalRequest($year, $season))->getAnime();
    }

    public static function schedule(string $day): array
    {
        $getter = self::dayGetter($day);
        if ($getter === null) {
            return [];
        }

        return self::client()->getSchedule(new ScheduleRequest())->{$getter}();
    }

    public static function dayGetter(string $day): ?string
    {
        return match (strtolower($day)) {
            'monday' => 'getMonday',
            'tuesday' => 'getTuesday',
            'wednesday' => 'getWednesday',
            'thursday' => 'getThursday',
            'friday' => 'getFriday',
            'saturday' => 'getSaturday',
            'sunday' => 'getSunday',
            default => null,
        };
    }

    public static function dateFrom(mixed $item): ?string
    {
        if (method_exists($item, 'getStartDate') && $item->getStartDate()) {
            return $item->getStartDate()->format('Y-m-d');
        }
        if (method_exists($item, 'getAiringStart') && $item->getAiringStart()) {
            return $item->getAiringStart()->format('Y-m-d');
        }

        return null;
    }

    public static function dateTo(mixed $item): ?string
    {
        if (method_exists($item, 'getEndDate') && $item->getEndDate()) {
            return $item->getEndDate()->format('Y-m-d');
        }

        return null;
    }

    public static function rating(mixed $item): ?string
    {
        if (method_exists($item, 'getRated')) {
            $rated = $item->getRated();

            return $rated === null ? null : (string) $rated;
        }

        return null;
    }

    public static function coverUrl(mixed $item): ?string
    {
        if (! method_exists($item, 'getImages')) {
            return null;
        }
        $images = $item->getImages();
        if ($images === null || ! method_exists($images, 'getWebp')) {
            return null;
        }

        return $images->getWebp()?->getLargeImageUrl();
    }

    public static function status(mixed $item): ?string
    {
        if (method_exists($item, 'getStatus')) {
            return $item->getStatus();
        }
        if (method_exists($item, 'isAiring') && $item->isAiring()) {
            return 'Currently Airing';
        }
        if (method_exists($item, 'isPublishing') && $item->isPublishing()) {
            return 'Publishing';
        }

        return null;
    }

    public static function episodes(mixed $item, bool $manga): ?int
    {
        if ($manga && method_exists($item, 'getChapters')) {
            return $item->getChapters();
        }
        if (method_exists($item, 'getEpisodes')) {
            return $item->getEpisodes();
        }

        return null;
    }

    private static function malType(?string $type, bool $manga): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        $key = strtolower($type);
        $map = $manga ? self::MANGA_TYPES : self::ANIME_TYPES;

        return $map[$key] ?? $type;
    }
}
