<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait UseQueryBuilder
{
    /**
     * Merge legacy pagination params into request (page, per_page).
     * Returns validated per_page (1-100).
     */
    protected function normalizePerPage(Request $request, int $default = 15): int
    {
        $perPage = (int) $request->get('per_page', $default);

        return min(max($perPage, 1), 100);
    }
}
