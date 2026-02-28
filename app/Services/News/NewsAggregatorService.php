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

    private function log(): \Psr\Log\LoggerInterface
    {
        return Log::channel(self::LOG_CHANNEL);
    }

    private const ACTIVITY_LOG_NAME = 'news_scraper';

    private function logActivity(string $description, array $properties = []): void
    {
        try {
            activity()
                ->useLog(self::ACTIVITY_LOG_NAME)
                ->withProperties($properties)
                ->log($description);
        } catch (\Throwable $e) {
            $this->log()->warning('Failed to write activity log', [
                'description' => $description,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{exception: string, file: string, line: int, trace: string}
     */
    private function exceptionDetails(\Throwable $e): array
    {
        $details = [
            'exception' => get_debug_type($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        if ($e->getPrevious() !== null) {
            $details['previous_exception'] = get_debug_type($e->getPrevious());
            $details['previous_message'] = $e->getPrevious()->getMessage();
            $details['previous_file'] = $e->getPrevious()->getFile();
            $details['previous_line'] = $e->getPrevious()->getLine();
            $details['previous_trace'] = $e->getPrevious()->getTraceAsString();
        }

        return $details;
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
     * @return array{saved:int,skipped:int,errors:int,errors_detail:array<int,array{type:string,source?:string,message:string,url?:string,title?:string,exception?:string,file?:string,line?:string}>}
     */
    public function run(?int $limitPorFuente = null): array
    {
        $saved = 0;
        $skipped = 0;
        $errors = 0;
        $errorsDetail = [];

        /** @var array<string, NewsScraperInterface> $scrapers */
        $scrapers = $this->registry->all();
        $limit = $limitPorFuente ?? 10;

        $this->log()->info('News scraper run started', [
            'sources' => array_keys($scrapers),
            'per_source_limit' => $limit,
        ]);

        $this->logActivity('Scraper de noticias iniciado', [
            'controller_name' => 'ScraperNoticias',
            'per_source' => $limit,
            'sources' => array_keys($scrapers),
        ]);

        foreach ($scrapers as $sourceKey => $scraper) {
            try {
                $articles = $scraper->fetchLatest($limit);
                $count = $articles->count();
                $this->log()->info('Source fetched', ['source' => $sourceKey, 'articles' => $count]);
            } catch (\Throwable $e) {
                $errors++;
                $errorsDetail[] = [
                    'type' => 'fuente',
                    'source' => $sourceKey,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
                $this->log()->error('Scraper failed', [
                    'source' => $sourceKey,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->logActivity("Error en fuente de noticias: {$sourceKey}", array_merge(
                    [
                        'controller_name' => 'ScraperNoticias',
                        'source' => $sourceKey,
                        'error' => $e->getMessage(),
                    ],
                    $this->exceptionDetails($e)
                ));
                continue;
            }

            $result = $this->processArticles($articles);
            $saved += $result['saved'];
            $skipped += $result['skipped'];
            $errors += $result['errors'];
            foreach ($result['errors_detail'] as $err) {
                $errorsDetail[] = $err;
            }

            $this->log()->info('Source processed', [
                'source' => $sourceKey,
                'saved' => $result['saved'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
            $this->logActivity("Fuente {$sourceKey} procesada: {$result['saved']} guardadas, {$result['skipped']} omitidas, {$result['errors']} errores", [
                'controller_name' => 'ScraperNoticias',
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
            'errors_detail' => $errorsDetail,
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
     * @return array{saved:int,skipped:int,errors:int,errors_detail:array<int,array{type:string,source?:string,message:string,url?:string,title?:string,exception?:string,file?:string,line?:string}>}
     */
    protected function processArticles(Collection $articles): array
    {
        $saved = 0;
        $skipped = 0;
        $errors = 0;
        $errorsDetail = [];

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
                $errorsDetail[] = [
                    'type' => 'artículo',
                    'source' => $article->source,
                    'url' => $article->originalUrl,
                    'title' => $article->titleOriginal ?? $article->sourceArticleId ?? null,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
                $this->log()->error('Error processing article', [
                    'source' => $article->source,
                    'source_article_id' => $article->sourceArticleId,
                    'url' => $article->originalUrl,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->logActivity('Error procesando noticia: ' . ($article->titleOriginal ?? $article->sourceArticleId ?? 'sin título'), array_merge(
                    [
                        'controller_name' => 'ScraperNoticias',
                        'source' => $article->source,
                        'source_article_id' => $article->sourceArticleId,
                        'url' => $article->originalUrl,
                        'error' => $e->getMessage(),
                    ],
                    $this->exceptionDetails($e)
                ));
            }
        }

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
            'errors_detail' => $errorsDetail,
        ];
    }
}

