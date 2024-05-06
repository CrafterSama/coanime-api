<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Posts;

use App\Models\Post;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\WhereIdIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class PostSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     */
    public static string $model = Post::class;

    /**
     * The maximum include path depth.
     */
    protected int $maxDepth = 4;

    /**
     * Get the resource fields.
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('title')->sortable(),
            Str::make('excerpt'),
            Str::make('content'),
            Str::make('image'),
            Str::make('slug'),
            BelongsTo::make('users'),
            BelongsTo::make('categories'),
            BelongsToMany::make('tags'),
            BelongsToMany::make('titles'),
            DateTime::make('postponed_to')->sortable()->readOnly(),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            DateTime::make('deleted_at')->sortable()->readOnly(),
        ];
    }

    /**
     * Get the resource filters.
     */
    public function filters(): array
    {
        return [
            WhereIdIn::make($this),
        ];
    }

    /**
     * Get the resource paginator.
     */
    public function pagination(): ?Paginator
    {
        return PagePagination::make();
    }
}