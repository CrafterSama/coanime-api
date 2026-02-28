<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $previousMode = null;
        if ($driver === 'mysql') {
            $previousMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode ?? '';
            $relaxed = str_replace(['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'], '', $previousMode);
            $relaxed = trim(preg_replace('/,,+/', ',', $relaxed), ',');
            DB::statement('SET SESSION sql_mode = ?', [$relaxed]);
        }

        try {
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
        } finally {
            if ($driver === 'mysql' && $previousMode !== null) {
                DB::statement('SET SESSION sql_mode = ?', [$previousMode]);
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

