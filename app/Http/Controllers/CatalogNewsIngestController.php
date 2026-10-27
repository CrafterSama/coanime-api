<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CatalogNewsIngest;
use App\Support\CatalogNewsEditorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CatalogNewsIngestController extends Controller
{
    public function store(Request $request, CatalogNewsIngest $ingest): JsonResponse
    {
        $denied = $this->denyUnlessOwner();
        if ($denied !== null) {
            return $denied;
        }

        $limit = (int) $request->input('limit', 10);
        $limit = max(1, min($limit, 25));

        try {
            $result = $ingest->run($limit);
        } catch (RuntimeException $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'code' => 200,
            'message' => 'ok',
            ...$result,
        ], 200);
    }

    public function status(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessOwner();
        if ($denied !== null) {
            return $denied;
        }

        $editorial = $request->boolean('probe')
            ? CatalogNewsEditorial::probe()
            : CatalogNewsEditorial::status();

        return response()->json([
            'code' => 200,
            'editorial' => $editorial,
        ], 200);
    }

    private function denyUnlessOwner(): ?JsonResponse
    {
        if (! Auth::guard('api')->check()) {
            return response()->json(['code' => 401, 'message' => 'Unauthenticated'], 401);
        }
        if ((int) Auth::id() !== CatalogNewsIngest::USER_ID) {
            return response()->json(['code' => 403, 'message' => 'Forbidden'], 403);
        }

        return null;
    }
}
