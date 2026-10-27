<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PostsMysqlSchema
{
    public static function repairPostponedTo(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable('posts')) {
            return;
        }

        // ponytail: session sql_mode only — MySQL refuses ALTER while postponed_to defaults to 0000-00-00
        DB::statement("SET SESSION sql_mode = ''");
        DB::update("UPDATE `posts` SET `postponed_to` = NULL WHERE CAST(`postponed_to` AS CHAR) IN ('0000-00-00 00:00:00', '0000-00-00')");

        $col = collect(Schema::getColumns('posts'))->firstWhere('name', 'postponed_to');
        $type = strtolower((string) ($col['type_name'] ?? 'timestamp'));
        $sqlType = str_contains($type, 'datetime') ? 'DATETIME' : 'TIMESTAMP';
        DB::statement("ALTER TABLE `posts` MODIFY `postponed_to` {$sqlType} NULL DEFAULT NULL");
    }
}
