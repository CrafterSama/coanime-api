<?php

declare(strict_types=1);

namespace App\Http\Resources\JsonApi\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class TitleImageResource extends JsonApiResource
{
    /**
     * @var list<string>
     */
    public $relationships = [
        'titles' => TitleResource::class,
    ];

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'thumbnail' => $this->thumbnail,
            'title_id' => $this->title_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
