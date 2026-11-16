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

        if (! Schema::hasTable('posts')) {
            return;
        }

        $excerpt = collect(Schema::getColumns('posts'))->firstWhere('name', 'excerpt');
        $type = (string) ($excerpt['type'] ?? '');
        if (preg_match('/varchar\((\d+)\)/i', $type, $m) === 1 && (int) $m[1] >= 255) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->string('excerpt', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('excerpt', 140)->nullable()->change();
        });
    }
};
