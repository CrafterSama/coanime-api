<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Titles;

use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

class TitleResource extends JsonApiResource
{
    /**
     * Get the resource's relationships.
     *
     * @param  Request|null  $request
     */
    public function relationships($request): iterable
    {
        return [
            $this->relation('titles-image')->withoutLinks(),
        ];
    }
}