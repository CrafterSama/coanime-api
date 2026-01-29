<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Title;
use App\Services\MalTitleEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichTitleFromMalJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $titleId;

    public function __construct(int $titleId)
    {
        $this->titleId = $titleId;
    }

    public function uniqueId(): string
    {
        return 'enrich-title-' . $this->titleId;
    }

    public function handle(MalTitleEnrichmentService $service): void
    {
        $title = Title::with('type', 'genres')->find($this->titleId);
        if ($title === null) {
            return;
        }

        if ($title->type_id === null || $title->type === null) {
            Log::info('EnrichTitleFromMalJob: skipped (no type)', ['title_id' => $this->titleId]);
            return;
        }

        if (! $title->hasMissingMalInfo()) {
            Log::info('EnrichTitleFromMalJob: skipped (no missing info)', ['title_id' => $this->titleId]);
            return;
        }

        try {
            Title::withoutEvents(function () use ($service, $title) {
                $service->enrich($title);
            });
            Log::info('EnrichTitleFromMalJob: completed', ['title_id' => $this->titleId, 'name' => $title->name]);
        } catch (\Throwable $e) {
            Log::error('EnrichTitleFromMalJob: failed', [
                'title_id' => $this->titleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
