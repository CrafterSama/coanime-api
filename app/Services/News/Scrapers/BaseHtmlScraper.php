<?php

declare(strict_types=1);

namespace App\Services\News\Scrapers;

use App\DataTransferObjects\NewsArticleData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

abstract class BaseHtmlScraper implements NewsScraperInterface
{
    abstract public function getSourceKey(): string;

    /**
     * Selector principal de items en el listado.
     */
    abstract protected function getListItemSelector(): string;

    /**
     * Permite a cada scraper concretar cómo construir el DTO desde el nodo del listado.
     */
    abstract protected function mapListItem(Crawler $node): ?NewsArticleData;

    /**
     * Permite que el scraper concrete cómo completar detalle (contenido, imágenes, etc.).
     */
    protected function enrichWithDetail(NewsArticleData $article): NewsArticleData
    {
        $config = $this->getSourceConfig();
        $detailSelector = $config['detail']['content'] ?? null;
        if ($detailSelector === null || $detailSelector === '') {
            return $article;
        }

        try {
            $response = Http::withHeaders($this->defaultHeaders())
                ->timeout($config['timeout'] ?? 10)
                ->get($article->originalUrl);

            if (! $response->successful()) {
                return $article;
            }
        } catch (\Throwable) {
            return $article;
        }

        $crawler = new Crawler($response->body(), $this->getBaseUrl());
        $contentNode = $crawler->filter($detailSelector);
        if (! $contentNode->count()) {
            return $article;
        }

        $html = '';
        $images = [];
        $videos = [];

        foreach ($contentNode as $domElement) {
            $nodeCrawler = new Crawler($domElement, $this->getBaseUrl());
            $html .= $nodeCrawler->html();

            $nodeCrawler->filter('img')->each(function (Crawler $img) use (&$images): void {
                $src = $img->attr('src') ?: $img->attr('data-src');
                if ($src !== null && $src !== '') {
                    $images[] = $this->absolutizeUrl($src);
                }
            });

            $nodeCrawler->filter('iframe, video')->each(function (Crawler $vid) use (&$videos): void {
                $src = $vid->attr('src') ?: $vid->attr('data-src');
                if ($src !== null && $src !== '') {
                    $videos[] = $this->absolutizeUrl($src);
                }
            });
        }

        $article->contentHtmlOriginal = $html !== '' ? $html : $article->contentHtmlOriginal;
        $article->contentImagesUrls = array_values(array_unique(array_merge($article->contentImagesUrls, $images)));
        $article->contentVideosUrls = array_values(array_unique(array_merge($article->contentVideosUrls, $videos)));

        return $article;
    }

    public function fetchLatest(int $limit = 10): Collection
    {
        $config = $this->getSourceConfig();
        $perSourceLimit = (int) (Config::get('news.limits.per_source', 10));
        $limit = min($limit, $perSourceLimit);

        try {
            $response = Http::withHeaders($this->defaultHeaders())
                ->timeout($config['timeout'] ?? 10)
                ->get($config['list_url']);

            if (! $response->successful()) {
                return collect();
            }
        } catch (\Throwable) {
            return collect();
        }

        $crawler = new Crawler($response->body(), $this->getBaseUrl());
        $items = collect();

        $crawler->filter($this->getListItemSelector())->each(function (Crawler $node) use (&$items, $limit): void {
            if ($items->count() >= $limit) {
                return;
            }
            $mapped = $this->mapListItem($node);
            if ($mapped !== null) {
                $items->push($this->enrichWithDetail($mapped));
            }
        });

        return $items;
    }

    protected function getSourceConfig(): array
    {
        $key = $this->getSourceKey();
        $config = Config::get("news.sources.{$key}", []);

        return is_array($config) ? $config : [];
    }

    protected function getBaseUrl(): string
    {
        return (string) ($this->getSourceConfig()['base_url'] ?? '');
    }

    protected function defaultHeaders(): array
    {
        $config = $this->getSourceConfig();

        return [
            'User-Agent' => $config['user_agent'] ?? 'CoanimeNewsBot/1.0 (+https://coanime.net)',
        ];
    }

    protected function parseDateFromAttr(Crawler $node, string $selector, string $attrName): ?CarbonImmutable
    {
        $value = $node->filter($selector)->attr($attrName) ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDateFromText(Crawler $node, string $selector): ?CarbonImmutable
    {
        $text = trim((string) ($node->filter($selector)->text(null, false) ?? ''));
        if ($text === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function absolutizeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = rtrim($this->getBaseUrl(), '/');

        if (str_starts_with($url, '/')) {
            return $base.$url;
        }

        return $base.'/'.$url;
    }
}

