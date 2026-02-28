<?php

declare(strict_types=1);

namespace App\Services\News\Scrapers;

use App\DataTransferObjects\NewsArticleData;
use Carbon\CarbonImmutable;
use Symfony\Component\DomCrawler\Crawler;

class AnimeCornerScraper extends BaseHtmlScraper
{
    public function getSourceKey(): string
    {
        return 'animecorner';
    }

    protected function getListItemSelector(): string
    {
        return $this->getSourceConfig()['list']['item'] ?? 'article';
    }

    protected function mapListItem(Crawler $node): ?NewsArticleData
    {
        $config = $this->getSourceConfig();
        $list = $config['list'] ?? [];
        $titleSelector = $list['title'] ?? 'h2.entry-title a';

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

        $date = null;
        if (! empty($list['date_attr']) && ! empty($list['date_attr_name'])) {
            $date = $this->parseDateFromAttr($node, $list['date_attr'], $list['date_attr_name']);
        }
        if ($date === null && ! empty($list['date_text'])) {
            $date = $this->parseDateFromText($node, $list['date_text']);
        }
        if ($date === null) {
            $date = CarbonImmutable::now();
        }

        $image = null;
        if (! empty($list['image'])) {
            $image = $this->safeAttr($node, $list['image'], 'src');
            if ($image === null) {
                $image = $this->safeAttr($node, $list['image'], 'data-src');
            }
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

