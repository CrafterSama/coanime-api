<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EditorialManual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EditorialController extends Controller
{
    public function show(): JsonResponse
    {
        $denied = $this->denyUnlessAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $manual = EditorialManual::active();

        return response()->json([
            'code' => 200,
            'version' => $manual?->version,
            'payload' => $manual?->payload ?? EditorialManual::defaultPayload(),
            'updated_at' => $manual?->updated_at?->toIso8601String(),
        ]);
    }

    public function versions(): JsonResponse
    {
        $denied = $this->denyUnlessAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $rows = EditorialManual::query()
            ->orderByDesc('version')
            ->limit(50)
            ->get(['id', 'version', 'created_at', 'updated_at']);

        return response()->json([
            'code' => 200,
            'versions' => $rows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $data = $request->validate([
            'medium_name' => 'required|string|max:120',
            'declaration' => 'required|string|max:5000',
            'tone' => 'required|string|max:2000',
            'title_rules' => 'required|string|max:5000',
            'content_rules' => 'required|string|max:5000',
            'fidelity_rules' => 'required|string|max:5000',
            'html_rules' => 'required|string|max:5000',
            'profile' => 'nullable|array',
            'glossary' => 'nullable|array',
            'glossary.*.term' => 'required_with:glossary|string|max:255',
            'glossary.*.do_not_translate' => 'nullable|boolean',
            'glossary.*.official_es' => 'nullable|string|max:255',
        ]);

        $manual = EditorialManual::publish($data);

        return response()->json([
            'code' => 200,
            'message' => 'ok',
            'version' => $manual->version,
            'payload' => $manual->payload,
        ], 201);
    }

    private function denyUnlessAdmin(): ?JsonResponse
    {
        if (! Auth::guard('api')->check()) {
            return response()->json(['code' => 401, 'message' => 'Unauthenticated'], 401);
        }
        $user = Auth::user();
        if ($user === null || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            return response()->json(['code' => 403, 'message' => 'Forbidden'], 403);
        }

        return null;
    }
}
