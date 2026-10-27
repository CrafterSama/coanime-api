<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\V1\UserResource;
use App\Models\User;
use App\Support\JsonApiPagination;
use Illuminate\Http\Request;

class JsonApiUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($ids = JsonApiPagination::filterIds($request)) {
            $query->whereIn('id', $ids);
        }

        [$page, $perPage] = JsonApiPagination::fromRequest($request);

        return UserResource::collection(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function show(User $user)
    {
        return new UserResource($user);
    }
}
