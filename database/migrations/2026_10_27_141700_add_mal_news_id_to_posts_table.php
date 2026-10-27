<?php

declare(strict_types=1);

use App\Support\PostsMysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        PostsMysqlSchema::repairPostponedTo();

        if (Schema::hasColumn('posts', 'mal_news_id')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('mal_news_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('posts', 'mal_news_id')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['mal_news_id']);
            $table->dropColumn('mal_news_id');
        });
    }
};
