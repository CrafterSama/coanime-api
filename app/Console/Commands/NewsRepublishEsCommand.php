<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\CatalogNewsIngest;
use App\Support\CatalogNews;
use App\Support\CatalogNewsEditorial;
use App\Support\TitleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsRepublishEsCommand extends Command
{
    protected $signature = 'news:republish-es {--limit=3 : Max news items (1-25)}';

    protected $description = 'Clear cached news (incl. English leftovers), then re-ingest Spanish posts for coanime.net.';

    public function handle(CatalogNewsIngest $ingest): int
    {
        $limit = max(1, min((int) $this->option('limit'), 25));
        $this->info("Republishing Tenrai news in Spanish (limit={$limit}).");

        try {
            $ids = [];
            foreach (TitleCatalog::news($limit) as $raw) {
                if (! is_array($raw) || ! isset($raw['mal_id'])) {
                    continue;
                }
                $ids[(int) $raw['mal_id']] = true;
            }
            try {
                foreach (Post::query()->whereNotNull('mal_news_id')->pluck('mal_news_id') as $malId) {
                    $ids[(int) $malId] = true;
                }
            } catch (Throwable) {
            }
            foreach (array_keys($ids) as $malId) {
                CatalogNewsEditorial::forgetNewsCaches((int) $malId);
            }
            CatalogNews::forgetDisplayCaches();
            $this->info('Cleared caches for '.count($ids).' news id(s) + relevants.');

            $result = $ingest->run($limit);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::error('news:republish-es failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }

        $ed = $result['editorial'];
        $this->info('LLM: configured='.($ed['configured'] ? 'yes' : 'no')
            .' connected='.($ed['connected'] ? 'yes' : 'no'));
        if (! empty($ed['last_error'])) {
            $this->warn('LLM last_error: '.$ed['last_error']);
        }
        $this->info('created='.$result['created'].' updated='.$result['updated'].' skipped='.$result['skipped']);
        foreach ($result['items'] as $row) {
            $this->line(($row['mal_id'] ?? '?').' '.$row['action'].' '.$row['source']);
        }

        return self::SUCCESS;
    }
}
