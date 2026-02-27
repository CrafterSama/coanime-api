<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\DataTransferObjects\NewsArticleData;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class NewsMediaService
{
    public function __construct(
        private readonly ImageSelector $selector,
        private readonly BigJpgClient $bigJpgClient,
    ) {
    }

    public function attachFeaturedImage(Post $post, NewsArticleData $article): void
    {
        $candidates = $article->contentImagesUrls;
        if ($article->mainImageUrl !== null) {
            array_unshift($candidates, $article->mainImageUrl);
        }

        $best = $this->selector->pickBest($candidates);
        if ($best === null) {
            return;
        }

        $upscaled = $this->bigJpgClient->enlarge($best) ?? $best;

        try {
            $post
                ->addMediaFromUrl($upscaled)
                ->usingName("Post {$post->id} - {$post->title}")
                ->toMediaCollection('featured-image');
        } catch (\Throwable $e) {
            Log::warning('NewsMediaService: could not attach featured image', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

