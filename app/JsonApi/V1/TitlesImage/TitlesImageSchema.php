<?php

declare(strict_types=1);

namespace App\JsonApi\V1\TitlesImage;

use App\Models\TitleImage;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\WhereIdIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class TitlesImageSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     */
    public static string $model = TitleImage::class;

    /**
     * Get the resource fields.
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
            Str::make('thumbnail'),
            ID::make('title_id'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            DateTime::make('deleted_at')->sortable()->readOnly(),
            BelongsTo::make('titles')->type('Titles')->readOnly(),
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