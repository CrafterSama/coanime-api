<?php

declare(strict_types=1);

namespace App\Services\News;

use App\DataTransferObjects\NewsArticleData;
use App\Services\Translations\GoogleTranslateService;
use Illuminate\Support\Str;

class NewsNormalizer
{
    public function __construct(
        private readonly GoogleTranslateService $translator,
    ) {
    }

    public function normalize(NewsArticleData $article): NewsArticleData
    {
        if ($article->titleEs === null || trim($article->titleEs) === '') {
            $article->titleEs = $this->translator->translate($article->titleOriginal);
        }

        if ($article->contentHtmlOriginal !== null && $article->contentHtmlOriginal !== '') {
            $article->contentHtmlEs = $this->translator->translate(
                strip_tags($article->contentHtmlOriginal)
            );
        }

        if ($article->excerptEs === null || trim($article->excerptEs) === '') {
            $base = $article->contentHtmlEs ?? $article->titleEs ?? $article->titleOriginal;
            $article->excerptEs = Str::words(strip_tags((string) $base), 30);
        }

        return $article;
    }
}

