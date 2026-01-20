<?php

namespace App\Jobs;

use App\Services\AnimeScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeAnimesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnimeScraperService $scraper): void
    {
        try {
            $animes = $scraper->scrapeDirectory();

            // 👉 acá luego podrías persistir en BD
            // Anime::upsert(...)

            Log::info('Scraping de animes exitoso', [
                'count' => count($animes),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en scraping de animes', [
                'message' => $e->getMessage(),
            ]);

            throw $e; // permite retry
        }
    }
}