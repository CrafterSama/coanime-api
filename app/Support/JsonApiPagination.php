<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class JsonApiPagination
{
    /**
     * @return array{0: int, 1: int} [page, perPage]
     */
    public static function fromRequest(Request $request): array
    {
        $page = max((int) $request->input('page.number', $request->input('page', 1)), 1);
        $perPage = min(max((int) $request->input('page.size', 15), 1), 100);

        return [$page, $perPage];
    }

    /**
     * @return list<int|string>|null
     */
    public static function filterIds(Request $request): ?array
    {
        $raw = $request->input('filter.id');
        if ($raw === null || $raw === '') {
            return null;
        }

        $ids = array_filter(array_map('trim', explode(',', (string) $raw)));

        return $ids === [] ? null : array_values($ids);
    }
}
