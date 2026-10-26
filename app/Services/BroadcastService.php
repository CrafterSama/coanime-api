<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MalJikan;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BroadcastService
{
    public function getTodaySchedule(?string $day = null): array
    {
        $broadcast = $this->fetchSchedule($day ?? date('l'));

        foreach ($broadcast as $key => $value) {
            $broadcast[$key]['url'] = self::coanimeUrl((string) ($value['type'] ?? ''), (string) ($value['title'] ?? ''));
        }

        return $broadcast;
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
            $cards = MalJikan::schedule($day);

            return array_map(static function ($card): array {
                $images = method_exists($card, 'getImages') ? $card->getImages() : null;
                $webp = $images && method_exists($images, 'getWebp') ? $images->getWebp() : null;

                return [
                    'mal_id' => method_exists($card, 'getMalId') ? $card->getMalId() : null,
                    'title' => method_exists($card, 'getTitle') ? $card->getTitle() : '',
                    'type' => method_exists($card, 'getType') ? ($card->getType() ?? '') : '',
                    'score' => method_exists($card, 'getScore') ? $card->getScore() : null,
                    'images' => [
                        'webp' => [
                            'large_image_url' => $webp?->getLargeImageUrl(),
                        ],
                    ],
                ];
            }, $cards);
        } catch (Exception $e) {
            Log::error('Error fetching broadcast schedule from MAL', [
                'day' => $day,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
