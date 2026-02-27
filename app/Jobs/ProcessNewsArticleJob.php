<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\NewsArticleData;
use App\Services\News\NewsAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNewsArticleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public NewsArticleData $article
    ) {
    }

    public function handle(NewsAggregatorService $aggregator): void
    {
        $aggregator->runForSingleArticle($this->article);
    }
}

