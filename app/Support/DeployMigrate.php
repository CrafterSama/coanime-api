<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Throwable;

final class DeployMigrate
{
    /**
     * Errors that mean the schema change is already present (legacy / manual / re-run).
     */
    public static function isAlreadyApplied(string $message): bool
    {
        return (bool) preg_match(
            '/already exists|Duplicate column|Duplicate key name|Can\'t DROP|check that column\/key exists|SQLSTATE\[42S01\]|SQLSTATE\[42S21\]|SQLSTATE\[42000\].*1091|\b1050\b|\b1060\b|\b1061\b|\b1091\b/i',
            $message
        );
    }

    /**
     * Run pending migrations one-by-one. Skip & log those that fail as already applied.
     *
     * @return array{ran: list<string>, skipped: list<string>, failed: ?string}
     */
    public static function run(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $repository = $migrator->getRepository();
        $files = app(Filesystem::class);

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        // ponytail: session fix so 2026 posts ALTER does not die on zero-date default
        PostsMysqlSchema::repairPostponedTo();

        $all = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $repository->getRan();
        $pending = [];
        foreach ($all as $name => $path) {
            if (! in_array($name, $ran, true)) {
                $pending[$name] = $path;
            }
        }

        $result = ['ran' => [], 'skipped' => [], 'failed' => null];
        if ($pending === []) {
            return $result;
        }

        $batch = $repository->getNextBatchNumber();

        foreach ($pending as $name => $path) {
            try {
                $migration = $files->getRequire($path);
                if (! is_object($migration) || ! method_exists($migration, 'up')) {
                    throw new \RuntimeException("Invalid migration: {$name}");
                }
                $migration->up();
                $repository->log($name, $batch);
                $result['ran'][] = $name;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (self::isAlreadyApplied($message)) {
                    if (! in_array($name, $repository->getRan(), true)) {
                        $repository->log($name, $batch);
                    }
                    $result['skipped'][] = $name;

                    continue;
                }
                $result['failed'] = $name.': '.$message;

                return $result;
            }
        }

        return $result;
    }
}
