<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('source', 50)->nullable()->index()->after('image');
            $table->string('source_article_id', 191)->nullable()->index()->after('source');
            $table->string('source_url')->nullable()->after('source_article_id');
            $table->timestamp('source_published_at')->nullable()->after('source_url');
            $table->boolean('auto_generated')->default(false)->after('source_published_at');
            $table->boolean('needs_review')->default(false)->after('auto_generated');

            $table->unique(['source', 'source_article_id']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropUnique(['source', 'source_article_id']);

            $table->dropColumn([
                'source',
                'source_article_id',
                'source_url',
                'source_published_at',
                'auto_generated',
                'needs_review',
            ]);
        });
    }
};

