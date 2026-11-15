<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CatalogNewsIngest;
use App\Support\CatalogNewsEditorial;
use App\Support\TitleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsRepublishEsCommand extends Command
{
    protected $signature = 'news:republish-es {--limit=10 : Max news items (1-25)}';

    protected $description = 'Forget news caches for the Tenrai batch and re-ingest Spanish posts (user_id=1).';

    public function handle(CatalogNewsIngest $ingest): int
    {
        $limit = max(1, min((int) $this->option('limit'), 25));
        $this->info("Republishing Tenrai news in Spanish (limit={$limit}).");

        try {
            foreach (TitleCatalog::news($limit) as $raw) {
                if (! is_array($raw) || ! isset($raw['mal_id'])) {
                    continue;
                }
                CatalogNewsEditorial::forgetNewsCaches((int) $raw['mal_id']);
            }
            Cache::forget('catalog.news.relevants.3');

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
