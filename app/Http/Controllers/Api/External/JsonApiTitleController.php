<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\V1\TitleResource;
use App\Models\Title;
use App\Support\JsonApiPagination;
use Illuminate\Http\Request;

class JsonApiTitleController extends Controller
{
    public function index(Request $request)
    {
        $query = Title::query()->with('images');

        if ($ids = JsonApiPagination::filterIds($request)) {
            $query->whereIn('id', $ids);
        }

        [$page, $perPage] = JsonApiPagination::fromRequest($request);

        return TitleResource::collection(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function show(Title $title)
    {
        $title->load('images');

        return new TitleResource($title);
    }
}
