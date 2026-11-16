<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PostsMysqlSchema;
use Illuminate\Console\Command;

class FixPostsMysqlSchemaCommand extends Command
{
    protected $signature = 'posts:fix-mysql-schema';

    protected $description = 'Fix posts.postponed_to zero-date default, then run 2026 excerpt + mal_news_id migrations.';

    private const PATHS = [
        'database/migrations/2026_09_18_022719_change_posts_excerpt_length.php',
        'database/migrations/2026_10_27_141700_add_mal_news_id_to_posts_table.php',
    ];

    public function handle(): int
    {
        $this->info('Repairing posts.postponed_to…');
        PostsMysqlSchema::repairPostponedTo();

        $failed = false;
        foreach (self::PATHS as $path) {
            $this->info("migrate --path={$path}");
            if ($this->call('migrate', ['--path' => $path, '--force' => true]) !== self::SUCCESS) {
                $this->error("Failed: {$path}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
