<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CatalogNewsIngest;
use Illuminate\Console\Command;
use RuntimeException;

class NewsIngestTenraiCommand extends Command
{
    protected $signature = 'news:ingest-tenrai {--limit=10 : Max news items (1-25)}';
    protected $description = 'Ingest Tenrai news as Spanish posts (user_id=1). Skip English and human-edited rows.';

    public function handle(CatalogNewsIngest $ingest): int
    {
        $limit = max(1, min((int) $this->option('limit'), 25));
        $this->info("Ingesting Tenrai news (limit={$limit}).");

        try {
            $result = $ingest->run($limit);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

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
