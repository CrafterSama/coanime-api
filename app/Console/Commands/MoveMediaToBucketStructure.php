<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\MediaLibrary\BucketPathGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Move existing media files from the old path structure ({id}/ or prefix/{id}/)
 * to the bucket folder structure (posts/, titles/, magazine/, people/, companies/, events/, users/).
 *
 * Run this after switching to BucketPathGenerator so that files already in the
 * media table are physically moved to the correct S3 folders. After running,
 * you can remove any leftover files in the root or old paths.
 *
 * Usage:
 *   php artisan media:move-to-bucket-structure           # all media
 *   php artisan media:move-to-bucket-structure --dry-run  # show what would be moved
 *   php artisan media:move-to-bucket-structure --model=posts
 */
class MoveMediaToBucketStructure extends Command
{
    protected $signature = 'media:move-to-bucket-structure
                            {--dry-run : List moves without copying or deleting}
                            {--model= : Only move media for this model (Post, Title, User, etc.)}
                            {--limit= : Max number of media items to process}';

    protected $description = 'Move media files from old path structure to bucket folders (posts/, titles/, etc.)';

    private DefaultPathGenerator $oldPathGenerator;
    private BucketPathGenerator $newPathGenerator;

    public function __construct(DefaultPathGenerator $oldPathGenerator, BucketPathGenerator $newPathGenerator)
    {
        parent::__construct();
        $this->oldPathGenerator = $oldPathGenerator;
        $this->newPathGenerator = $newPathGenerator;
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $modelFilter = $this->option('model');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('DRY RUN: no files will be copied or deleted.');
        }

        $query = Media::query()->orderBy('id');

        if ($modelFilter !== null && $modelFilter !== '') {
            $class = $this->normalizeModelClass($modelFilter);
            if ($class === null) {
                $this->error("Unknown model: {$modelFilter}");

                return self::FAILURE;
            }
            $query->where('model_type', $class);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $mediaItems = $query->get();
        $total = $mediaItems->count();
        $moved = 0;
        $skipped = 0;
        $errors = 0;

        $this->info("Processing {$total} media item(s)...");

        foreach ($mediaItems as $media) {
            $oldBase = rtrim($this->oldPathGenerator->getPath($media), '/');
            $newBase = rtrim($this->newPathGenerator->getPath($media), '/');

            if ($oldBase === $newBase) {
                $skipped++;
                continue;
            }

            $disk = Storage::disk($media->disk ?? 's3');

            if (!$disk->exists($oldBase)) {
                $this->line("  [skip] Media #{$media->id}: old path does not exist: {$oldBase}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [would move] #{$media->id} {$oldBase} -> {$newBase}");
                $moved++;
                continue;
            }

            try {
                $this->moveMediaFiles($disk, $oldBase, $newBase);
                $this->line("  ✓ Media #{$media->id} -> {$newBase}");
                $moved++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Media #{$media->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done. Moved: {$moved}, Skipped: {$skipped}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function moveMediaFiles(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $oldBase, string $newBase): void
    {
        $allFiles = $disk->allFiles($oldBase);

        foreach ($allFiles as $path) {
            $relative = substr($path, strlen($oldBase) + 1);
            $newPath = $newBase . '/' . $relative;

            $contents = $disk->get($path);
            $disk->put($newPath, $contents, ['visibility' => $disk->getVisibility($path)]);
        }

        foreach (array_reverse($allFiles) as $path) {
            $disk->delete($path);
        }

        $disk->deleteDirectory($oldBase);
    }

    private function normalizeModelClass(string $model): ?string
    {
        $models = [
            'post' => \App\Models\Post::class,
            'posts' => \App\Models\Post::class,
            'title' => \App\Models\Title::class,
            'titles' => \App\Models\Title::class,
            'user' => \App\Models\User::class,
            'users' => \App\Models\User::class,
            'magazine' => \App\Models\Magazine::class,
            'magazines' => \App\Models\Magazine::class,
            'people' => \App\Models\People::class,
            'company' => \App\Models\Company::class,
            'companies' => \App\Models\Company::class,
            'event' => \App\Models\Event::class,
            'events' => \App\Models\Event::class,
        ];

        return $models[strtolower($model)] ?? null;
    }
}
