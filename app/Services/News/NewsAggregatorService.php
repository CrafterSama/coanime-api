<?php

declare(strict_types=1);

namespace App\Services\News;

use App\DataTransferObjects\NewsArticleData;
use App\Models\Post;
use App\Services\Images\NewsMediaService;
use App\Services\News\Scrapers\NewsScraperInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewsAggregatorService
{
    private const LOG_CHANNEL = 'news_scraper';

    private function log(): \Illuminate\Log\LogManager
    {
        return Log::channel(self::LOG_CHANNEL);
    }
    public function __construct(
        private readonly NewsScraperRegistry $registry,
        private readonly NewsNormalizer $normalizer,
        private readonly CategoryResolver $categoryResolver,
        private readonly NewsMediaService $mediaService,
    ) {
    }

    /**
     * Ejecuta todos los scrappers registrados y procesa las noticias resultantes.
     *
     * @param  int|null  $limitPorFuente
     * @return array{saved:int,skipped:int,errors:int}
     */
    public function run(?int $limitPorFuente = null): array
    {
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        /** @var array<string, NewsScraperInterface> $scrapers */
        $scrapers = $this->registry->all();
        $limit = $limitPorFuente ?? 10;

        $this->log()->info('News scraper run started', [
            'sources' => array_keys($scrapers),
            'per_source_limit' => $limit,
        ]);

        foreach ($scrapers as $sourceKey => $scraper) {
            try {
                $articles = $scraper->fetchLatest($limit);
                $count = $articles->count();
                $this->log()->info('Source fetched', ['source' => $sourceKey, 'articles' => $count]);
            } catch (\Throwable $e) {
                $errors++;
                $this->log()->error('Scraper failed', [
                    'source' => $sourceKey,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                continue;
            }

            $result = $this->processArticles($articles);
            $saved += $result['saved'];
            $skipped += $result['skipped'];
            $errors += $result['errors'];

            $this->log()->info('Source processed', [
                'source' => $sourceKey,
                'saved' => $result['saved'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
        }

        $this->log()->info('News scraper run finished', [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Procesa una sola noticia (usado por jobs individuales).
     *
     * @return array{saved:int,skipped:int,errors:int}
     */
    public function runForSingleArticle(NewsArticleData $article): array
    {
        return $this->processArticles(collect([$article]));
    }

    /**
     * @param  Collection<int, NewsArticleData>  $articles
     * @return array{saved:int,skipped:int,errors:int}
     */
    protected function processArticles(Collection $articles): array
    {
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($articles as $article) {
            try {
                $normalized = $this->normalizer->normalize($article);
                $category = $this->categoryResolver->resolve($normalized);

                if ($category === null) {
                    $skipped++;
                    continue;
                }

                $existing = Post::where('source', $normalized->source)
                    ->where('source_article_id', $normalized->sourceArticleId)
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                DB::beginTransaction();

                $post = new Post();
                $post->title = $normalized->titleEs ?? $normalized->titleOriginal;
                $post->excerpt = $normalized->excerptEs;
                $post->content = $normalized->contentHtmlEs ?? $normalized->contentHtmlOriginal ?? '';
                $post->category_id = $category->id;
                $post->user_id = 1;
                $post->slug = \Str::slug($post->title);
                $post->approved = \App\Enums\PostApproved::NO->value;
                $post->draft = \App\Enums\PostDraft::DRAFT->value;
                $post->source = $normalized->source;
                $post->source_article_id = $normalized->sourceArticleId;
                $post->source_url = $normalized->originalUrl;
                $post->source_published_at = $normalized->publishedAt?->toDateTimeString();
                $post->auto_generated = true;
                $post->needs_review = true;

                $post->save();

                $this->mediaService->attachFeaturedImage($post, $normalized);

                DB::commit();
                $saved++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors++;
                $this->log()->error('Error processing article', [
                    'source' => $article->source,
                    'source_article_id' => $article->sourceArticleId,
                    'url' => $article->originalUrl,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}

