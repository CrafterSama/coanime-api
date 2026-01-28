<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JikanSeasonSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class JikanSyncSeasonCommand extends Command
{
    protected $signature = 'jikan:sync-season
                            {--year= : Year (optional; with --season: single sync)}
                            {--season= : Season: winter|spring|summer|fall (optional; with --year: single sync)}
                            {--page= : Page 1–3 (optional; used only in single sync, default 1)}
                            {--single : Force single (year, season, page) sync instead of current+next, pages 1–3}';

    protected $description = 'Sync titles from Jikan MAL API. Default: current + next season, pages 1–3. Use --year/--season/--page or --single for one (year, season, page). Scheduled daily at 03:00.';

    private const PAGES_FULL = [1, 2, 3];

    public function handle(JikanSeasonSyncService $service): int
    {
        $yearOpt = $this->option('year');
        $seasonOpt = $this->option('season');
        $pageOpt = $this->option('page');
        $single = $this->option('single');

        $useFull = ! $single && ($yearOpt === null || $yearOpt === '') && ($seasonOpt === null || $seasonOpt === '');

        if ($useFull) {
            return $this->runFullSync($service);
        }

        $page = $pageOpt !== null && $pageOpt !== '' ? (int) $pageOpt : 1;
        $page = $page >= 1 ? $page : 1;
        $options = ['page' => $page];
        if ($yearOpt !== null && $yearOpt !== '') {
            $options['year'] = (int) $yearOpt;
        }
        if ($seasonOpt !== null && $seasonOpt !== '') {
            $options['season'] = $seasonOpt;
        }

        $this->info('Running Jikan season sync (single): year=' . ($options['year'] ?? 'current') . ', season=' . ($options['season'] ?? 'current') . ", page={$page}.");

        try {
            $result = $service->sync($options);
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('jikan:sync-season failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return Command::FAILURE;
        }

        $this->emitResult($result, $options);

        return Command::SUCCESS;
    }

    private function runFullSync(JikanSeasonSyncService $service): int
    {
        $currentYear = (int) date('Y');
        $currentSeason = $service->currentSeason();
        $nextYear = $service->nextSeasonYear();
        $nextSeason = $service->nextSeason();

        $batches = [
            ['year' => $currentYear, 'season' => $currentSeason],
            ['year' => $nextYear, 'season' => $nextSeason],
        ];

        $this->info("Running Jikan full sync: current {$currentYear} {$currentSeason} + next {$nextYear} {$nextSeason}, pages 1–3.");

        $total = ['saved' => 0, 'skipped' => 0, 'invalid_type' => 0, 'errors' => []];

        foreach ($batches as $batch) {
            foreach (self::PAGES_FULL as $page) {
                $options = ['year' => $batch['year'], 'season' => $batch['season'], 'page' => $page];
                $this->line("  → {$batch['year']} {$batch['season']} page {$page}");

                try {
                    $result = $service->sync($options);
                } catch (\Throwable $e) {
                    $this->warn("    Failed: " . $e->getMessage());
                    Log::warning('jikan:sync-season batch failed', ['options' => $options, 'exception' => $e->getMessage()]);
                    $total['errors'][] = "{$batch['year']} {$batch['season']} p{$page}: " . $e->getMessage();
                    continue;
                }

                $total['saved'] += $result['saved'];
                $total['skipped'] += $result['skipped'];
                $total['invalid_type'] += $result['invalid_type'];
                foreach ($result['errors'] as $err) {
                    $total['errors'][] = $err;
                    $this->warn('    ' . $err);
                }
            }
        }

        $this->info("Done. Saved: {$total['saved']}, Skipped: {$total['skipped']}, Invalid type: {$total['invalid_type']}.");
        Log::info('jikan:sync-season full completed', $total);

        return Command::SUCCESS;
    }

    private function emitResult(array $result, array $options): void
    {
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
    }
}
