<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EditorialManual;
use Illuminate\Database\Seeder;

class EditorialManualSeeder extends Seeder
{
    public function run(): void
    {
        if (EditorialManual::query()->exists()) {
            return;
        }

        EditorialManual::publish(EditorialManual::defaultPayload());
    }
}
