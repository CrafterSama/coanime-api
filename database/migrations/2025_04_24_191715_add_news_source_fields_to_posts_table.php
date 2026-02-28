<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (Schema::hasColumn('posts', 'postponed_to')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->timestamp('postponed_to')->nullable()->default(null)->change();
            });
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'source')) {
                $table->string('source', 50)->nullable()->index();
            }
            if (! Schema::hasColumn('posts', 'source_article_id')) {
                $table->string('source_article_id', 191)->nullable()->index();
            }
            if (! Schema::hasColumn('posts', 'source_url')) {
                $table->string('source_url')->nullable();
            }
            if (! Schema::hasColumn('posts', 'source_published_at')) {
                $table->timestamp('source_published_at')->nullable();
            }
            if (! Schema::hasColumn('posts', 'auto_generated')) {
                $table->boolean('auto_generated')->default(false);
            }
            if (! Schema::hasColumn('posts', 'needs_review')) {
                $table->boolean('needs_review')->default(false);
            }
        });

        if (Schema::hasColumn('posts', 'source') && Schema::hasColumn('posts', 'source_article_id')) {
            try {
                Schema::table('posts', function (Blueprint $table): void {
                    $table->unique(['source', 'source_article_id']);
                });
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table): void {
            try {
                $table->dropUnique(['source', 'source_article_id']);
            } catch (\Throwable $e) {
                // Ignore if unique index does not exist
            }

            $columns = ['source', 'source_article_id', 'source_url', 'source_published_at', 'auto_generated', 'needs_review'];
            $existing = array_filter($columns, fn (string $col): bool => Schema::hasColumn('posts', $col));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};

