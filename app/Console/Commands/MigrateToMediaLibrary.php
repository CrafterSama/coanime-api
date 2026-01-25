<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Magazine;
use App\Models\Post;
use App\Models\Title;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateToMediaLibrary extends Command
{
    protected $signature = 'media:migrate 
                            {--dry-run : Run without actually migrating data}
                            {--model= : Migrate specific model (posts, users, titles, magazines)}
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
            : ['posts', 'users', 'titles', 'magazines'];

        $totalMigrated = 0;

        foreach ($models as $model) {
            $this->info("📦 Processing {$model}...");
            
            try {
                $count = match($model) {
                    'posts' => $this->migratePosts($dryRun, $force),
                    'users' => $this->migrateUsers($dryRun, $force),
                    'titles' => $this->migrateTitles($dryRun, $force),
                    'magazines' => $this->migrateMagazines($dryRun, $force),
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
                $post->addMediaFromUrl($imageUrl)
                    ->usingName("Post {$post->id} - {$post->title}")
                    ->usingFileName($this->getFileNameFromUrl($imageUrl))
                    ->toMediaCollection('featured-image');

                $this->line("  ✓ Migrated post #{$post->id}");
                $count++;
            } catch (\Exception $e) {
                $this->warn("  ✗ Failed post #{$post->id}: " . $e->getMessage());
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
                        $user->addMediaFromUrl($user->profile_photo_path)
                            ->usingName("User {$user->id} - {$user->name} - Avatar")
                            ->usingFileName($this->getFileNameFromUrl($user->profile_photo_path))
                            ->toMediaCollection('avatar');
                        $count++;
                    } catch (\Exception $e) {
                        $this->warn("  ✗ Failed user #{$user->id} avatar: " . $e->getMessage());
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
                        $user->addMediaFromUrl($coverPath)
                            ->usingName("User {$user->id} - {$user->name} - Cover")
                            ->usingFileName($this->getFileNameFromUrl($coverPath))
                            ->toMediaCollection('cover');
                        $count++;
                    } catch (\Exception $e) {
                        $this->warn("  ✗ Failed user #{$user->id} cover: " . $e->getMessage());
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
                $media = $title->addMediaFromUrl($imageUrl)
                    ->usingName("Title {$title->id} - {$title->name}")
                    ->usingFileName($this->getFileNameFromUrl($imageUrl))
                    ->toMediaCollection('cover');

                if ($title->images->thumbnail && $title->images->thumbnail !== $imageUrl) {
                    $media->setCustomProperty('original_thumbnail', $title->images->thumbnail);
                    $media->save();
                }

                $this->line("  ✓ Migrated title #{$title->id}");
                $count++;
            } catch (\Exception $e) {
                $this->warn("  ✗ Failed title #{$title->id}: " . $e->getMessage());
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
                $magazine->addMediaFromUrl($imageUrl)
                    ->usingName("Magazine {$magazine->id} - {$magazine->name}")
                    ->usingFileName($this->getFileNameFromUrl($imageUrl))
                    ->toMediaCollection('cover');

                $this->line("  ✓ Migrated magazine #{$magazine->id}");
                $count++;
            } catch (\Exception $e) {
                $this->warn("  ✗ Failed magazine #{$magazine->id}: " . $e->getMessage());
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
}
