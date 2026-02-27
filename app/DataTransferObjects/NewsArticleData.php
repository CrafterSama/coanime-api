<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;

class NewsArticleData
{
    public function __construct(
        public string $source,
        public string $sourceArticleId,
        public string $originalUrl,
        public string $titleOriginal,
        public ?string $titleEs = null,
        public ?string $excerptEs = null,
        public ?string $contentHtmlOriginal = null,
        public ?string $contentHtmlEs = null,
        public ?CarbonImmutable $publishedAt = null,
        public array $tags = [],
        public ?string $sourceCategory = null,
        public ?string $mainImageUrl = null,
        public array $contentImagesUrls = [],
        public array $contentVideosUrls = [],
    ) {
    }

    public static function makeIdFromUrl(string $source, string $url): string
    {
        return hash('sha256', $source.'|'.$url);
    }
}

