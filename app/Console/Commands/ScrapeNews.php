<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\News\NewsAggregatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeNews extends Command
{
    protected $signature = 'news:scrape {--per-source=10 : Número máximo de noticias por fuente}';

    protected $description = 'Scrapea noticias de anime/manga desde fuentes externas y crea borradores en la base de datos';

    public function handle(NewsAggregatorService $aggregator): int
    {
        $perSource = (int) $this->option('per-source');

        Log::channel('news_scraper')->info('news:scrape command started', ['per_source' => $perSource]);

        try {
            $result = $aggregator->run($perSource);

            Log::channel('news_scraper')->info('news:scrape command finished', [
                'saved' => $result['saved'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);

            $this->info("Noticias procesadas. Guardadas: {$result['saved']}, Saltadas: {$result['skipped']}, Errores: {$result['errors']}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('news_scraper')->error('news:scrape command failed', [
                'message' => $e->getMessage(),
                'exception' => get_debug_type($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

