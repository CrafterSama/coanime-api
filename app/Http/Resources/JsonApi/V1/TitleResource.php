<?php

declare(strict_types=1);

namespace App\Http\Resources\JsonApi\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class TitleResource extends JsonApiResource
{
    /**
     * @var list<string>
     */
    public $relationships = [
        'images' => TitleImageResource::class,
    ];

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'episodies' => $this->episodies,
            'slug' => $this->slug,
            'sinopsis' => $this->sinopsis,
            'other_titles' => $this->other_titles,
            'trailer_url' => $this->trailer_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
