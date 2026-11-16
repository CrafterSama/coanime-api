<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DeployMigrate;
use Illuminate\Console\Command;
use Throwable;

class MigrateDeployCommand extends Command
{
    protected $signature = 'migrate:deploy';

    protected $description = 'Run pending migrations for deploy; skip ones that fail because schema already exists, continue with the rest.';

    public function handle(): int
    {
        $this->info('Running migrate:deploy…');

        try {
            $result = DeployMigrate::run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['ran'] as $name) {
            $this->info("Migrated: {$name}");
        }
        foreach ($result['skipped'] as $name) {
            $this->warn("Skipped (already applied): {$name}");
        }

        if ($result['failed'] !== null) {
            $this->error('Failed: '.$result['failed']);

            return self::FAILURE;
        }

        if ($result['ran'] === [] && $result['skipped'] === []) {
            $this->info('Nothing to migrate.');
        } else {
            $this->info('Done. ran='.count($result['ran']).' skipped='.count($result['skipped']));
        }

        return self::SUCCESS;
    }
}
