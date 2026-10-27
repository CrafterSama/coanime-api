<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\V1\PostResource;
use App\Models\Post;
use App\Support\JsonApiPagination;
use Illuminate\Http\Request;

class JsonApiPostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::query()->with([
            'users',
            'categories',
            'tags',
            'titles.images',
        ]);

        if ($ids = JsonApiPagination::filterIds($request)) {
            $query->whereIn('id', $ids);
        }

        [$page, $perPage] = JsonApiPagination::fromRequest($request);

        return PostResource::collection(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function show(Post $post)
    {
        $post->load(['users', 'categories', 'tags', 'titles.images']);

        return new PostResource($post);
    }
}
