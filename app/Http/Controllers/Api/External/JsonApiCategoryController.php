<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonApi\V1\CategoryResource;
use App\Models\Category;
use App\Support\JsonApiPagination;
use Illuminate\Http\Request;

class JsonApiCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        if ($ids = JsonApiPagination::filterIds($request)) {
            $query->whereIn('id', $ids);
        }

        [$page, $perPage] = JsonApiPagination::fromRequest($request);

        return CategoryResource::collection(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function show(Category $category)
    {
        return new CategoryResource($category);
    }
}
