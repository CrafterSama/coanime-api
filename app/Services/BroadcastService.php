<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TitleCatalog;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BroadcastService
{
    public function getTodaySchedule(?string $day = null): array
    {
        return $this->fetchSchedule($day ?? date('l'));
    }

    public function getScheduleByDay(?string $day = null): array
    {
        return $this->fetchSchedule($day ?? strtolower(date('l')));
    }

    public static function coanimeUrl(string $type, string $title): string
    {
        return 'https://coanime.net/ecma/titulos/'.Str::slug($type).'/'.Str::slug($title);
    }

    private function fetchSchedule(string $day): array
    {
        try {
            // ponytail: cache global 1h; bajar TTL si hay que invalidar al instante
            $cards = Cache::remember('catalog.schedule.'.strtolower($day), 3600, function () use ($day) {
                return TitleCatalog::schedule($day);
            });

            if ($cards === []) {
                Log::warning('Empty catalog schedule', ['day' => $day]);
            }

            return array_map(static function (array $card): array {
                $card['url'] = self::coanimeUrl((string) ($card['type'] ?? ''), (string) ($card['title'] ?? ''));

                return $card;
            }, $cards);
        } catch (Exception $e) {
            Log::warning('Error fetching catalog schedule', [
                'day' => $day,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
