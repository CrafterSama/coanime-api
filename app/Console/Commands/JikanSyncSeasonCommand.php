<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JikanSeasonSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class JikanSyncSeasonCommand extends Command
{
    protected $signature = 'jikan:sync-season
                            {--year= : Year (default: current)}
                            {--season= : Season: winter|spring|summer|fall (default: current)}
                            {--page=1 : Page to fetch (default: 1)}';

    protected $description = 'Sync titles from Jikan MAL API by season (same logic as add-titles-season). Scheduled daily at 03:00.';

    public function handle(JikanSeasonSyncService $service): int
    {
        $year = $this->option('year');
        $season = $this->option('season');
        $page = (int) ($this->option('page') ?? 1) ?: 1;

        $options = ['page' => $page];
        if ($year !== null && $year !== '') {
            $options['year'] = (int) $year;
        }
        if ($season !== null && $season !== '') {
            $options['season'] = $season;
        }

        $this->info('Running Jikan season sync: year=' . ($options['year'] ?? 'current') . ', season=' . ($options['season'] ?? 'current') . ", page={$page}.");

        try {
            $result = $service->sync($options);
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('jikan:sync-season failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return Command::FAILURE;
        }

        foreach ($result['errors'] as $err) {
            $this->warn($err);
            Log::warning('jikan:sync-season error', ['message' => $err]);
        }

        $this->info("Done. Saved: {$result['saved']}, Skipped: {$result['skipped']}, Invalid type: {$result['invalid_type']}.");
        Log::info('jikan:sync-season completed', [
            'saved' => $result['saved'],
            'skipped' => $result['skipped'],
            'invalid_type' => $result['invalid_type'],
            'options' => $options,
        ]);

        return Command::SUCCESS;
    }
}
