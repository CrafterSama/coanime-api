<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListPlaceholderMedia extends Command
{
    protected $signature = 'media:list-placeholders 
                            {--model= : Filter by model type (Post, User, Title, Magazine)}
                            {--collection= : Filter by collection name}';

    protected $description = 'List all placeholder media records that need file replacement';

    public function handle(): int
    {
        $query = Media::whereJsonContains('custom_properties->is_placeholder', true);

        $modelType = $this->option('model');
        if ($modelType) {
            $query->where('model_type', 'App\\Models\\' . $modelType);
        }

        $collection = $this->option('collection');
        if ($collection) {
            $query->where('collection_name', $collection);
        }

        $placeholders = $query->with('model')->get();

        if ($placeholders->isEmpty()) {
            $this->info('✅ No placeholder media found!');
            return Command::SUCCESS;
        }

        $this->info("📋 Found {$placeholders->count()} placeholder media records:");
        $this->newLine();

        $headers = ['ID', 'Model', 'Model ID', 'Collection', 'Original URL', 'Error'];
        $rows = [];

        foreach ($placeholders as $media) {
            $modelName = class_basename($media->model_type);
            $modelId = $media->model_id;
            $originalUrl = $media->getCustomProperty('original_url', 'N/A');
            $error = substr($media->getCustomProperty('error', 'N/A'), 0, 50);

            $rows[] = [
                $media->id,
                $modelName,
                $modelId,
                $media->collection_name,
                substr($originalUrl, 0, 60) . '...',
                $error,
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('💡 Tip: Use the original URL to manually replace these files later.');

        return Command::SUCCESS;
    }
}
