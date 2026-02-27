<?php

declare(strict_types=1);

namespace App\Services\News;

use App\DataTransferObjects\NewsArticleData;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryResolver
{
    public function resolve(NewsArticleData $article): ?Category
    {
        $map = config('news.category_map', []);

        if ($article->sourceCategory !== null) {
            $slug = Str::slug(Str::lower($article->sourceCategory));
            $internalSlug = $map[$slug] ?? $map[Str::lower($article->sourceCategory)] ?? null;
            if ($internalSlug !== null) {
                $category = Category::where('slug', $internalSlug)->first();
                if ($category !== null) {
                    return $category;
                }
            }
        }

        $text = Str::lower(strip_tags(($article->titleEs ?? $article->titleOriginal).' '.$article->contentHtmlEs));

        $keywordMap = [
            'manhwa' => 'corea',
            'k-drama' => 'corea',
            'korea' => 'corea',
            'manhua' => 'china',
            'china' => 'china',
            'live-action' => 'live-action',
            'live action' => 'live-action',
            'videojuego' => 'videojuegos',
            'game' => 'videojuegos',
            'manga' => 'manga',
            'anime' => 'anime',
            'light novel' => 'light-novel',
        ];

        foreach ($keywordMap as $keyword => $slug) {
            if (Str::contains($text, $keyword)) {
                $category = Category::where('slug', $slug)->first();
                if ($category !== null) {
                    return $category;
                }
            }
        }

        return Category::where('slug', 'noticias-anime')->first();
    }
}

