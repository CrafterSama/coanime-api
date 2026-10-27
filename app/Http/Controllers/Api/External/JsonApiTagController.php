<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\V1\TagResource;
use App\Models\Tag;
use App\Support\JsonApiPagination;
use Illuminate\Http\Request;

class JsonApiTagController extends Controller
{
    public function index(Request $request)
    {
        $query = Tag::query();

        if ($ids = JsonApiPagination::filterIds($request)) {
            $query->whereIn('id', $ids);
        }

        [$page, $perPage] = JsonApiPagination::fromRequest($request);

        return TagResource::collection(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function show(Tag $tag)
    {
        return new TagResource($tag);
    }
}
