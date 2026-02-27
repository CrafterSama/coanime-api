<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\News\NewsAggregatorService;
use Illuminate\Console\Command;

class ScrapeNews extends Command
{
    protected $signature = 'news:scrape {--per-source=10 : Número máximo de noticias por fuente}';

    protected $description = 'Scrapea noticias de anime/manga desde fuentes externas y crea borradores en la base de datos';

    public function handle(NewsAggregatorService $aggregator): int
    {
        $perSource = (int) $this->option('per-source');
        $result = $aggregator->run($perSource);

        $this->info("Noticias procesadas. Guardadas: {$result['saved']}, Saltadas: {$result['skipped']}, Errores: {$result['errors']}.");

        return self::SUCCESS;
    }
}

