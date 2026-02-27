<?php

declare(strict_types=1);

namespace App\Services\News\Scrapers;

use App\DataTransferObjects\NewsArticleData;
use Carbon\CarbonImmutable;
use Symfony\Component\DomCrawler\Crawler;

class OtakuNewsScraper extends BaseHtmlScraper
{
    public function getSourceKey(): string
    {
        return 'otakuusa';
    }

    protected function getListItemSelector(): string
    {
        return $this->getSourceConfig()['list']['item'] ?? '.post';
    }

    protected function mapListItem(Crawler $node): ?NewsArticleData
    {
        $config = $this->getSourceConfig();
        $list = $config['list'] ?? [];

        $titleNode = $node->filter($list['title'] ?? 'h2 a, h3 a, .entry-title a');
        $title = trim((string) ($titleNode->text(null, false) ?? ''));
        $link = $titleNode->attr('href') ?? null;

        if ($title === '' || $link === null) {
            return null;
        }

        $excerpt = '';
        if (! empty($list['excerpt']) && $node->filter($list['excerpt'])->count()) {
            $excerpt = trim((string) $node->filter($list['excerpt'])->text(null, false));
        }

        $date = null;
        if (! empty($list['date_attr']) && ! empty($list['date_attr_name'])) {
            $date = $this->parseDateFromAttr($node, $list['date_attr'], $list['date_attr_name']);
        }
        if ($date === null) {
            $date = CarbonImmutable::now();
        }

        $imageNode = ! empty($list['image']) ? $node->filter($list['image']) : null;
        $image = null;
        if ($imageNode !== null && $imageNode->count()) {
            $image = $imageNode->attr('src') ?: $imageNode->attr('data-src') ?: '';
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

