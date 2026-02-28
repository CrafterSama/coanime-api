<?php

declare(strict_types=1);

namespace App\Services\News\Scrapers;

use App\DataTransferObjects\NewsArticleData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class MalNewsScraper extends BaseHtmlScraper
{
    public function getSourceKey(): string
    {
        return 'myanimelist';
    }

    protected function getListItemSelector(): string
    {
        return $this->getSourceConfig()['list']['item'] ?? '.news-unit';
    }

    /**
     * Si el listado con .news-unit no devuelve ítems (MAL puede haber cambiado el HTML),
     * intenta obtener noticias desde enlaces a /news/ID.
     */
    public function fetchLatest(int $limit = 10): Collection
    {
        $items = parent::fetchLatest($limit);

        if ($items->isNotEmpty()) {
            return $items;
        }

        $fallbackSelector = $this->getSourceConfig()['list']['item_fallback_link'] ?? null;
        if ($fallbackSelector === null || $fallbackSelector === '') {
            return $items;
        }

        return $this->fetchLatestByNewsLinks($limit, $fallbackSelector);
    }

    /**
     * Obtiene noticias desde enlaces a /news/{id} en la página de listado.
     */
    protected function fetchLatestByNewsLinks(int $limit, string $linkSelector): Collection
    {
        $config = $this->getSourceConfig();
        $perSourceLimit = (int) Config::get('news.limits.per_source', 10);
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
        $seenUrls = [];

        $crawler->filter($linkSelector)->each(function (Crawler $node) use (&$items, $limit, &$seenUrls): void {
            if ($items->count() >= $limit) {
                return;
            }

            try {
                $href = $node->attr('href');
            } catch (\Throwable) {
                return;
            }
            if ($href === null || $href === '') {
                return;
            }

            $absoluteUrl = $this->absolutizeUrl($href);
            if ($absoluteUrl === null) {
                return;
            }
            // Solo enlaces a noticia individual: /news/73917655
            if (! preg_match('#/news/(\d+)(?:/|$)#', $absoluteUrl)) {
                return;
            }
            if (isset($seenUrls[$absoluteUrl])) {
                return;
            }
            $seenUrls[$absoluteUrl] = true;

            $title = null;
            try {
                $title = trim((string) $node->text(null, false));
            } catch (\Throwable) {
                return;
            }
            if ($title === '') {
                return;
            }

            $sourceArticleId = NewsArticleData::makeIdFromUrl($this->getSourceKey(), $absoluteUrl);
            $dto = new NewsArticleData(
                source: $this->getSourceKey(),
                sourceArticleId: $sourceArticleId,
                originalUrl: $absoluteUrl,
                titleOriginal: $title,
                titleEs: null,
                excerptEs: null,
                contentHtmlOriginal: null,
                contentHtmlEs: null,
                publishedAt: CarbonImmutable::now(),
                tags: [],
                sourceCategory: null,
                mainImageUrl: null,
                contentImagesUrls: [],
                contentVideosUrls: [],
            );

            $items->push($this->enrichWithDetail($dto));
        });

        return $items;
    }

    protected function mapListItem(Crawler $node): ?NewsArticleData
    {
        $config = $this->getSourceConfig();
        $list = $config['list'] ?? [];
        $titleSelector = $list['title'] ?? '.title a';

        $title = $this->safeText($node, $titleSelector);
        $link = $this->safeAttr($node, $titleSelector, 'href');

        if ($title === null || $title === '' || $link === null) {
            return null;
        }

        $excerpt = '';
        if (! empty($list['excerpt'])) {
            $excerptText = $this->safeText($node, $list['excerpt']);
            $excerpt = $excerptText !== null ? $excerptText : '';
        }

        // MAL no da fecha en formato estándar, usamos fecha actual como aproximación
        $date = CarbonImmutable::now();

        $image = null;
        if (! empty($list['image'])) {
            $image = $this->safeAttr($node, $list['image'], 'src');
            if ($image === null) {
                $image = $this->safeAttr($node, $list['image'], 'data-src');
            }
        }
        if ($image !== null && str_starts_with($image, '//')) {
            $image = 'https:'.$image;
        }

        $absoluteLink = $this->absolutizeUrl($link);
        $absoluteImage = $image ? $this->absolutizeUrl($image) : null;

        $sourceKey = $this->getSourceKey();
        $sourceArticleId = NewsArticleData::makeIdFromUrl($sourceKey, $absoluteLink ?? $link);

        return new NewsArticleData(
            source: $sourceKey,
            sourceArticleId: $sourceArticleId,
            originalUrl: $absoluteLink ?? $link,
            titleOriginal: $title,
            titleEs: null,
            excerptEs: $excerpt !== '' ? $excerpt : null,
            contentHtmlOriginal: null,
            contentHtmlEs: null,
            publishedAt: $date,
            tags: [],
            sourceCategory: null,
            mainImageUrl: $absoluteImage,
            contentImagesUrls: $absoluteImage ? [$absoluteImage] : [],
            contentVideosUrls: [],
        );
    }
}

