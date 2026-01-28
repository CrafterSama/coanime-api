<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\EnrichTitleFromMalJob;
use App\Models\Title;
use Illuminate\Support\Facades\Log;

class TitleObserver
{
    /**
     * Handle the Title "saved" event. Dispatch enrichment job when title has missing MAL-info.
     */
    public function saved(Title $title): void
    {
        if (! $title->hasMissingMalInfo()) {
            return;
        }

        try {
            EnrichTitleFromMalJob::dispatch($title->id);
            Log::info('TitleObserver: dispatched EnrichTitleFromMalJob', ['title_id' => $title->id, 'name' => $title->name]);
        } catch (\Throwable $e) {
            Log::warning('TitleObserver: could not dispatch EnrichTitleFromMalJob', [
                'title_id' => $title->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
