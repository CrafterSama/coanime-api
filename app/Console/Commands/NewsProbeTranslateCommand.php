<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CatalogNewsEditorial;
use App\Support\TitleCatalog;
use Illuminate\Console\Command;

class NewsProbeTranslateCommand extends Command
{
    protected $signature = 'news:probe {id? : Tenrai news mal_id} {--limit=1 : How many from /news if no id (1-5)}';

    protected $description = 'Fetch Tenrai news and print LLM vs Google Translate per field. Does not save posts.';

    public function handle(): int
    {
        $ids = $this->ids();
        if ($ids === []) {
            $this->error('No news ids.');

            return self::FAILURE;
        }

        foreach ($ids as $malId) {
            $this->printOne($malId);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function ids(): array
    {
        $id = $this->argument('id');
        if ($id !== null && $id !== '') {
            return [(int) $id];
        }

        $limit = max(1, min((int) $this->option('limit'), 5));
        $ids = [];
        foreach (TitleCatalog::news($limit) as $raw) {
            if (is_array($raw) && isset($raw['mal_id'])) {
                $ids[] = (int) $raw['mal_id'];
            }
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    private function printOne(int $malId): void
    {
        $detail = TitleCatalog::newsById($malId);
        if ($detail === null) {
            $this->error("=== mal_id={$malId} === not found");

            return;
        }

        $html = '';
        $content = $detail['content'] ?? null;
        if (is_array($content) && is_string($content['html'] ?? null)) {
            $html = $content['html'];
        }
        $enTitle = (string) ($detail['title'] ?? '');
        $enExcerpt = (string) ($detail['excerpt'] ?? '');
        $row = CatalogNewsEditorial::probeArticle($malId, $enTitle, $enExcerpt, $html);

        $this->info("=== mal_id={$malId} ===");
        foreach (['title', 'excerpt', 'content'] as $field) {
            $this->line("-- {$field} --");
            $this->line('EN:  '.$row['en'][$field]);
            $this->line('LLM: '.$this->llmField($row, $field));
            $this->line('GT:  '.$row['gt'][$field]);
        }

        $llm = $row['llm'];
        $gt = $row['gt'];
        $this->line('isSpanish LLM: '.$this->yesNo($llm !== null && CatalogNewsEditorial::isSpanish(
            $llm['title'],
            $llm['excerpt'],
            $llm['content'],
            $enTitle,
            $enExcerpt
        )));
        $this->line('isSpanish GT:  '.$this->yesNo(CatalogNewsEditorial::isSpanish(
            $gt['title'],
            $gt['excerpt'],
            $gt['content'],
            $enTitle,
            $enExcerpt
        )));
    }

    /**
     * @param  array{llm: array{title: string, excerpt: string, content: string}|null, llm_error: string|null}  $row
     */
    private function llmField(array $row, string $field): string
    {
        if ($row['llm'] === null) {
            return 'fail: '.($row['llm_error'] ?? 'empty');
        }

        return $row['llm'][$field];
    }

    private function yesNo(bool $ok): string
    {
        return $ok ? 'yes' : 'no';
    }
}
