<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Services\News\Scrapers\AnimeCornerScraper;
use App\Services\News\Scrapers\MalNewsScraper;
use App\Services\News\Scrapers\NewsScraperInterface;
use App\Services\News\Scrapers\OtakuNewsScraper;
use Illuminate\Support\Arr;

class NewsScraperRegistry
{
    /**
     * @var array<string, class-string<NewsScraperInterface>>
     */
    private array $map = [
        'animecorner' => AnimeCornerScraper::class,
        'otakuusa' => OtakuNewsScraper::class,
        'myanimelist' => MalNewsScraper::class,
    ];

    public function get(string $sourceKey): ?NewsScraperInterface
    {
        $class = Arr::get($this->map, $sourceKey);
        if ($class === null) {
            return null;
        }

        return app($class);
    }

    /**
     * @return array<string, NewsScraperInterface>
     */
    public function all(): array
    {
        $instances = [];
        foreach ($this->map as $key => $class) {
            $instances[$key] = app($class);
        }

        return $instances;
    }
}

