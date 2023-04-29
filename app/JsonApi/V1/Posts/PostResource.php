<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Posts;

use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

class PostResource extends JsonApiResource
{
    /**
     * Get the resource's relationships.
     *
     * @param  Request|null  $request
     */
    public function relationships($request): iterable
    {
        return [
            $this->relation('users')->withoutLinks(),
            $this->relation('categories')->withoutLinks(),
            $this->relation('titles')->withoutLinks(),
            $this->relation('tags')->withoutLinks(),
        ];
    }
}