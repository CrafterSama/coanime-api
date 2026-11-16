<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\Title;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MigrateToMediaLibrary extends Command
{
    protected $signature = 'media:migrate 
                            {--dry-run : Run without actually migrating data}
                            {--model= : Migrate specific model (posts, users, titles, magazines, people, events)}
                            {--force : Force migration even if media already exists}';

    protected $description = 'Migrate existing images from database fields to Spatie Media Library';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificModel = $this->option('model');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be migrated');
        }

        $this->info('🚀 Starting migration to Media Library...');
        $this->newLine();

        $models = $specificModel 
            ? [$specificModel] 
            : ['posts', 'users', 'titles', 'magazines', 'people', 'events'];

        $totalMigrated = 0;

        foreach ($models as $model) {
            $this->info("📦 Processing {$model}...");
            
            try {
                $count = match($model) {
                    'posts' => $this->migratePosts($dryRun, $force),
                    'users' => $this->migrateUsers($dryRun, $force),
                    'titles' => $this->migrateTitles($dryRun, $force),
                    'magazines' => $this->migrateMagazines($dryRun, $force),
                    'people' => $this->migratePeople($dryRun, $force),
                    'events' => $this->migrateEvents($dryRun, $force),
                    default => 0,
                };

                $totalMigrated += $count;
                $this->info("✅ {$model}: {$count} items migrated");
            } catch (\Exception $e) {
                $this->error("❌ Error migrating {$model}: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        $this->info("🎉 Migration completed! Total: {$totalMigrated} items");
        
        return Command::SUCCESS;
    }

    private function migratePosts(bool $dryRun, bool $force): int
    {
        $posts = Post::whereNotNull('image')
            ->where('image', '!=', '')
            ->where('image', '!=', 'https://api.coanime.net/storage/images/posts/')
            ->get();

        $count = 0;

        foreach ($posts as $post) {
            if (!$force && $post->getFirstMedia('featured-image')) {
                continue;
            }

            $imageUrl = $post->image;
            
            if (empty($imageUrl) || !$this->isValidUrl($imageUrl)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would migrate post #{$post->id}: {$imageUrl}");
                $count++;
                continue;
            }

            try {
                // Try to use S3 directly if URL points to S3
                if ($this->isS3Url($imageUrl)) {
                    try {
                        $this->migrateFromS3($post, $imageUrl, 'featured-image', "Post {$post->id} - {$post->title}");
                        $this->line("  ✓ Migrated post #{$post->id} (from S3)");
                    } catch (\Exception $e) {
                        // If S3 fails, try HTTP or create placeholder
                        $this->createPlaceholderMedia($post, $imageUrl, 'featured-image', "Post {$post->id} - {$post->title}", [
                            'model_type' => 'Post',
                            'model_id' => $post->id,
                            'model_title' => $post->title,
                            'error' => $e->getMessage(),
                        ]);
                        $this->line("  ⚠ Created placeholder for post #{$post->id} (S3 access failed)");
                    }
                } else {
                    $post->addMediaFromUrl($imageUrl)
                        ->usingName("Post {$post->id} - {$post->title}")
                        ->usingFileName($this->getFileNameFromUrl($imageUrl))
                        ->toMediaCollection('featured-image');
                    $this->line("  ✓ Migrated post #{$post->id}");
                }
                $count++;
            } catch (\Exception $e) {
                // Create placeholder even if migration fails
                $this->createPlaceholderMedia($post, $imageUrl, 'featured-image', "Post {$post->id} - {$post->title}", [
                    'model_type' => 'Post',
                    'model_id' => $post->id,
                    'model_title' => $post->title,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ⚠ Created placeholder for post #{$post->id} (URL not accessible): " . substr($e->getMessage(), 0, 60));
                $count++;
            }
        }

        return $count;
    }

    private function migrateUsers(bool $dryRun, bool $force): int
    {
        $users = User::where(function($query) {
            $query->whereNotNull('profile_photo_path')
                  ->where('profile_photo_path', '!=', '')
                  ->orWhereNotNull('profile_cover_path')
                  ->where('profile_cover_path', '!=', '')
                  ->orWhereNotNull('cover_photo_path')
                  ->where('cover_photo_path', '!=', '');
        })->get();

        $count = 0;

        foreach ($users as $user) {
            // Profile photo
            if (!empty($user->profile_photo_path) && $this->isValidUrl($user->profile_photo_path)) {
                if (!$force && $user->getFirstMedia('avatar')) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would migrate user #{$user->id} avatar");
                    $count++;
                } else {
                    try {
                        if ($this->isS3Url($user->profile_photo_path)) {
                            try {
                                $this->migrateFromS3($user, $user->profile_photo_path, 'avatar', "User {$user->id} - {$user->name} - Avatar");
                            } catch (\Exception $e) {
                                $this->createPlaceholderMedia($user, $user->profile_photo_path, 'avatar', "User {$user->id} - {$user->name} - Avatar", [
                                    'model_type' => 'User',
                                    'model_id' => $user->id,
                                    'model_name' => $user->name,
                                    'error' => $e->getMessage(),
                                ]);
                                $this->line("  ⚠ Created placeholder for user #{$user->id} avatar");
                            }
                        } else {
                            $user->addMediaFromUrl($user->profile_photo_path)
                                ->usingName("User {$user->id} - {$user->name} - Avatar")
                                ->usingFileName($this->getFileNameFromUrl($user->profile_photo_path))
                                ->toMediaCollection('avatar');
                        }
                        $count++;
                    } catch (\Exception $e) {
                        $this->createPlaceholderMedia($user, $user->profile_photo_path, 'avatar', "User {$user->id} - {$user->name} - Avatar", [
                            'model_type' => 'User',
                            'model_id' => $user->id,
                            'model_name' => $user->name,
                            'error' => $e->getMessage(),
                        ]);
                        $this->warn("  ⚠ Created placeholder for user #{$user->id} avatar: " . substr($e->getMessage(), 0, 60));
                        $count++;
                    }
                }
            }

            // Cover photo
            $coverPath = $user->profile_cover_path ?? $user->cover_photo_path;
            if (!empty($coverPath) && $this->isValidUrl($coverPath)) {
                if (!$force && $user->getFirstMedia('cover')) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would migrate user #{$user->id} cover");
                    $count++;
                } else {
                    try {
                        if ($this->isS3Url($coverPath)) {
                            try {
                                $this->migrateFromS3($user, $coverPath, 'cover', "User {$user->id} - {$user->name} - Cover");
                            } catch (\Exception $e) {
                                $this->createPlaceholderMedia($user, $coverPath, 'cover', "User {$user->id} - {$user->name} - Cover", [
                                    'model_type' => 'User',
                                    'model_id' => $user->id,
                                    'model_name' => $user->name,
                                    'error' => $e->getMessage(),
                                ]);
                                $this->line("  ⚠ Created placeholder for user #{$user->id} cover");
                            }
                        } else {
                            $user->addMediaFromUrl($coverPath)
                                ->usingName("User {$user->id} - {$user->name} - Cover")
                                ->usingFileName($this->getFileNameFromUrl($coverPath))
                                ->toMediaCollection('cover');
                        }
                        $count++;
                    } catch (\Exception $e) {
                        $this->createPlaceholderMedia($user, $coverPath, 'cover', "User {$user->id} - {$user->name} - Cover", [
                            'model_type' => 'User',
                            'model_id' => $user->id,
                            'model_name' => $user->name,
                            'error' => $e->getMessage(),
                        ]);
                        $this->warn("  ⚠ Created placeholder for user #{$user->id} cover: " . substr($e->getMessage(), 0, 60));
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function migrateTitles(bool $dryRun, bool $force): int
    {
        $titles = Title::with('images')->get();
        $count = 0;

        foreach ($titles as $title) {
            if (!$title->images || empty($title->images->name)) {
                continue;
            }

            $imageUrl = $title->images->name;

            if (!$this->isValidUrl($imageUrl)) {
                continue;
            }

            if (!$force && $title->getFirstMedia('cover')) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would migrate title #{$title->id}");
                $count++;
                continue;
            }

            try {
                if ($this->isS3Url($imageUrl)) {
                    try {
                        $media = $this->migrateFromS3($title, $imageUrl, 'cover', "Title {$title->id} - {$title->name}");
                    } catch (\Exception $e) {
                        $media = $this->createPlaceholderMedia($title, $imageUrl, 'cover', "Title {$title->id} - {$title->name}", [
                            'model_type' => 'Title',
                            'model_id' => $title->id,
                            'model_name' => $title->name,
                            'error' => $e->getMessage(),
                        ]);
                        $this->line("  ⚠ Created placeholder for title #{$title->id}");
                    }
                } else {
                    $media = $title->addMediaFromUrl($imageUrl)
                        ->usingName("Title {$title->id} - {$title->name}")
                        ->usingFileName($this->getFileNameFromUrl($imageUrl))
                        ->toMediaCollection('cover');
                }

                if ($title->images->thumbnail && $title->images->thumbnail !== $imageUrl) {
                    $media->setCustomProperty('original_thumbnail', $title->images->thumbnail);
                    $media->save();
                }

                $this->line("  ✓ Migrated title #{$title->id}");
                $count++;
            } catch (\Exception $e) {
                $media = $this->createPlaceholderMedia($title, $imageUrl, 'cover', "Title {$title->id} - {$title->name}", [
                    'model_type' => 'Title',
                    'model_id' => $title->id,
                    'model_name' => $title->name,
                    'error' => $e->getMessage(),
                ]);
                if ($title->images->thumbnail && $title->images->thumbnail !== $imageUrl) {
                    $media->setCustomProperty('original_thumbnail', $title->images->thumbnail);
                    $media->save();
                }
                $this->warn("  ⚠ Created placeholder for title #{$title->id}: " . substr($e->getMessage(), 0, 60));
                $count++;
            }
        }

        return $count;
    }

    private function migrateMagazines(bool $dryRun, bool $force): int
    {
        $magazines = Magazine::with('image')->get();
        $count = 0;

        foreach ($magazines as $magazine) {
            if (!$magazine->image || empty($magazine->image->name)) {
                continue;
            }

            $imageUrl = $magazine->image->name;

            if (!$this->isValidUrl($imageUrl)) {
                continue;
            }

            if (!$force && $magazine->getFirstMedia('cover')) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would migrate magazine #{$magazine->id}");
                $count++;
                continue;
            }

            try {
                if ($this->isS3Url($imageUrl)) {
                    try {
                        $this->migrateFromS3($magazine, $imageUrl, 'cover', "Magazine {$magazine->id} - {$magazine->name}");
                        $this->line("  ✓ Migrated magazine #{$magazine->id} (from S3)");
                    } catch (\Exception $e) {
                        $this->createPlaceholderMedia($magazine, $imageUrl, 'cover', "Magazine {$magazine->id} - {$magazine->name}", [
                            'model_type' => 'Magazine',
                            'model_id' => $magazine->id,
                            'model_name' => $magazine->name,
                            'error' => $e->getMessage(),
                        ]);
                        $this->line("  ⚠ Created placeholder for magazine #{$magazine->id}");
                    }
                } else {
                    $magazine->addMediaFromUrl($imageUrl)
                        ->usingName("Magazine {$magazine->id} - {$magazine->name}")
                        ->usingFileName($this->getFileNameFromUrl($imageUrl))
                        ->toMediaCollection('cover');
                    $this->line("  ✓ Migrated magazine #{$magazine->id}");
                }
                $count++;
            } catch (\Exception $e) {
                $this->createPlaceholderMedia($magazine, $imageUrl, 'cover', "Magazine {$magazine->id} - {$magazine->name}", [
                    'model_type' => 'Magazine',
                    'model_id' => $magazine->id,
                    'model_name' => $magazine->name,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ⚠ Created placeholder for magazine #{$magazine->id}: " . substr($e->getMessage(), 0, 60));
                $count++;
            }
        }

        return $count;
    }

    private function migratePeople(bool $dryRun, bool $force): int
    {
        $people = People::whereNotNull('image')
            ->where('image', '!=', '')
            ->get();

        $count = 0;
        $baseDirs = [
            storage_path('app/public/images/encyclopedia/people'),
            public_path('images/encyclopedia/people'),
            public_path('storage/images/encyclopedia/people'),
        ];

        foreach ($people as $person) {
            if (! $force && $person->getFirstMedia('default')) {
                continue;
            }

            $raw = $person->getRawOriginal('image');
            $raw = ltrim((string) $raw, '/');
            if (empty($raw)) {
                continue;
            }

            if ($this->isValidUrl($raw)) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would migrate people #{$person->id} (from URL)");
                    $count++;
                    continue;
                }
                try {
                    $person->addMediaFromUrl($raw)
                        ->usingName("People {$person->id} - {$person->name}")
                        ->usingFileName($this->getFileNameFromUrl($raw))
                        ->toMediaCollection('default');
                    $this->line("  ✓ Migrated people #{$person->id}");
                    $count++;
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Skip people #{$person->id}: {$e->getMessage()}");
                }
                continue;
            }

            $path = null;
            foreach ($baseDirs as $dir) {
                $candidate = $dir.\DIRECTORY_SEPARATOR.$raw;
                if (File::exists($candidate)) {
                    $path = $candidate;
                    break;
                }
            }

            if (! $path) {
                $urlFallback = rtrim(config('app.url'), '/').'/storage/images/encyclopedia/people/'.ltrim($raw, '/');
                if (! $dryRun && $this->isValidUrl($urlFallback)) {
                    try {
                        $person->addMediaFromUrl($urlFallback)
                            ->usingName("People {$person->id} - {$person->name}")
                            ->usingFileName(basename($raw))
                            ->toMediaCollection('default');
                        $this->line("  ✓ Migrated people #{$person->id} (from URL fallback)");
                        $count++;
                    } catch (\Exception $e) {
                        $this->warn("  ⚠ Skip people #{$person->id}: file not found, URL fallback failed - {$e->getMessage()}");
                    }
                } else {
                    $this->warn("  ⚠ Skip people #{$person->id}: file not found ({$raw})");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would migrate people #{$person->id}");
                $count++;
                continue;
            }

            try {
                $person->addMedia($path)
                    ->usingName("People {$person->id} - {$person->name}")
                    ->usingFileName(basename($raw))
                    ->toMediaCollection('default');
                $this->line("  ✓ Migrated people #{$person->id}");
                $count++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Skip people #{$person->id}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    private function migrateEvents(bool $dryRun, bool $force): int
    {
        $events = Event::whereNotNull('image')
            ->where('image', '!=', '')
            ->get();

        $count = 0;
        $baseDirs = [
            storage_path('app/public/images/events'),
            public_path('images/events'),
            public_path('storage/images/events'),
        ];

        foreach ($events as $event) {
            if (! $force && $event->getFirstMedia('default')) {
                continue;
            }

            $raw = $event->getRawOriginal('image');
            $raw = ltrim((string) $raw, '/');
            if (empty($raw)) {
                continue;
            }

            if ($this->isValidUrl($raw)) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would migrate event #{$event->id} (from URL)");
                    $count++;
                    continue;
                }
                try {
                    $event->addMediaFromUrl($raw)
                        ->usingName("Event {$event->id} - {$event->name}")
                        ->usingFileName($this->getFileNameFromUrl($raw))
                        ->toMediaCollection('default');
                    $this->line("  ✓ Migrated event #{$event->id}");
                    $count++;
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Skip event #{$event->id}: {$e->getMessage()}");
                }
                continue;
            }

            $path = null;
            foreach ($baseDirs as $dir) {
                $candidate = $dir.\DIRECTORY_SEPARATOR.$raw;
                if (File::exists($candidate)) {
                    $path = $candidate;
                    break;
                }
            }

            if (! $path) {
                $urlFallback = rtrim(config('app.url'), '/').'/storage/images/events/'.ltrim($raw, '/');
                if (! $dryRun && $this->isValidUrl($urlFallback)) {
                    try {
                        $event->addMediaFromUrl($urlFallback)
                            ->usingName("Event {$event->id} - {$event->name}")
                            ->usingFileName(basename($raw))
                            ->toMediaCollection('default');
                        $this->line("  ✓ Migrated event #{$event->id} (from URL fallback)");
                        $count++;
                    } catch (\Exception $e) {
                        $this->warn("  ⚠ Skip event #{$event->id}: file not found, URL fallback failed - {$e->getMessage()}");
                    }
                } else {
                    $this->warn("  ⚠ Skip event #{$event->id}: file not found ({$raw})");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would migrate event #{$event->id}");
                $count++;
                continue;
            }

            try {
                $event->addMedia($path)
                    ->usingName("Event {$event->id} - {$event->name}")
                    ->usingFileName(basename($raw))
                    ->toMediaCollection('default');
                $this->line("  ✓ Migrated event #{$event->id}");
                $count++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Skip event #{$event->id}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    private function isValidUrl(string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $invalidPatterns = ['placeholder', 'default', 'no-image', 'null', 'undefined'];
        $urlLower = strtolower($url);
        
        foreach ($invalidPatterns as $pattern) {
            if (str_contains($urlLower, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function getFileNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            if (str_contains(strtolower($url), '.webp')) {
                $filename .= '.webp';
            } elseif (str_contains(strtolower($url), '.png')) {
                $filename .= '.png';
            } else {
                $filename .= '.jpg';
            }
        }

        return $filename;
    }

    /**
     * Check if URL points to S3 storage
     */
    private function isS3Url(string $url): bool
    {
        $s3Patterns = [
            'api.coanime.net/storage',
            's3.amazonaws.com',
            '.s3.',
            'storage/images',
        ];

        foreach ($s3Patterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Migrate from S3 directly without HTTP download
     */
    private function migrateFromS3($model, string $url, string $collection, string $name)
    {
        // Extract path from URL
        $path = $this->extractS3PathFromUrl($url);
        
        if (!$path) {
            throw new \Exception("Could not extract S3 path from URL: {$url}");
        }

        // Check if file exists in S3
        if (!Storage::disk('s3')->exists($path)) {
            throw new \Exception("File does not exist in S3: {$path}");
        }

        // Get file from S3
        $fileContents = Storage::disk('s3')->get($path);
        $fileName = $this->getFileNameFromUrl($url);

        // Create temporary file
        $tempPath = sys_get_temp_dir() . '/' . uniqid('media_', true) . '_' . $fileName;
        file_put_contents($tempPath, $fileContents);

        try {
            // Add media from temporary file
            $media = $model->addMedia($tempPath)
                ->usingName($name)
                ->usingFileName($fileName)
                ->toMediaCollection($collection);

            return $media;
        } finally {
            // Clean up temporary file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Extract S3 path from URL
     */
    private function extractS3PathFromUrl(string $url): ?string
    {
        // Handle api.coanime.net/storage/images/posts/...
        if (str_contains($url, 'api.coanime.net/storage/')) {
            $path = parse_url($url, PHP_URL_PATH);
            // Remove leading /
            $path = ltrim($path, '/');
            // Remove 'storage/' prefix if present
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8); // Remove 'storage/'
            }
            return $path;
        }

        // Handle direct S3 URLs
        if (str_contains($url, '.s3.') || str_contains($url, 's3.amazonaws.com')) {
            $parsed = parse_url($url);
            return ltrim($parsed['path'] ?? '', '/');
        }

        return null;
    }

    /**
     * Create a placeholder media record when URL is not accessible
     * This allows tracking which images need to be replaced
     */
    private function createPlaceholderMedia($model, string $url, string $collection, string $name, array $metadata = []): Media
    {
        $fileName = $this->getFileNameFromUrl($url);
        $mimeType = $this->guessMimeType($fileName);

        // Create media record manually
        $media = new Media();
        $media->model_type = get_class($model);
        $media->model_id = $model->id;
        $media->uuid = \Illuminate\Support\Str::uuid()->toString();
        $media->collection_name = $collection;
        $media->name = $name;
        $media->file_name = $fileName;
        $media->mime_type = $mimeType;
        $media->disk = 's3'; // Use S3 disk even if file doesn't exist yet
        $media->size = 0; // File size unknown
        $media->manipulations = [];
        $media->custom_properties = array_merge([
            'original_url' => $url,
            'is_placeholder' => true,
            'file_not_accessible' => true,
            'migration_date' => now()->toIso8601String(),
        ], $metadata);
        $media->generated_conversions = [];
        $media->responsive_images = [];
        $media->order_column = 1;
        $media->save();

        return $media;
    }

    /**
     * Guess MIME type from file extension
     */
    private function guessMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
}
