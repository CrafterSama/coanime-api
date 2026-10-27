<?php

declare(strict_types=1);

namespace App\Http\Resources\JsonApi\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'profile_photo_path' => $this->profile_photo_path,
            'profile_cover_path' => $this->profile_cover_path,
            'bio' => $this->bio,
            'username' => $this->username,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'tiktok' => $this->tiktok,
            'pinterest' => $this->pinterest,
            'devianart' => $this->devianart,
            'tumblr' => $this->tumblr,
            'website' => $this->website,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
