<?php

declare(strict_types=1);

namespace App\JsonApi\V1;

use LaravelJsonApi\Core\Server\Server as BaseServer;

class Server extends BaseServer
{
    /**
     * The base URI namespace for this server.
     */
    protected string $baseUri = '/api/external';

    /**
     * Bootstrap the server when it is handling an HTTP request.
     */
    public function serving(): void
    {
        // no-op
    }

    /**
     * Get the server's list of schemas.
     */
    protected function allSchemas(): array
    {
        return [
            // @TODO
            Posts\PostSchema::class,
            Users\UserSchema::class,
            Tags\TagSchema::class,
            Categories\CategorySchema::class,
            Titles\TitleSchema::class,
            TitlesImage\TitlesImageSchema::class,
        ];
    }
}