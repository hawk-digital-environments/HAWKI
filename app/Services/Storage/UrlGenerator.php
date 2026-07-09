<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Storage\Values\StoredFile;
use Illuminate\Support\Facades\URL;

/**
 * Generates the public URL for a stored file by delegating to a named Laravel route.
 *
 * The route name is injected at construction time. `StorageServiceProvider` chooses between
 * `web.storage.proxy` and `api.external_app.storage.proxy` based on the current usage context,
 * so the same storage service can serve both the web frontend and the external-app API without
 * knowing which route is active.
 */
readonly class UrlGenerator
{
    public function __construct(
        private string $routeName
    )
    {
    }

    /**
     * Generates the URL to stream the given file through the storage proxy controller.
     * The identifier string is passed as the `identifier` route parameter.
     */
    public function generate(StoredFile $file): string
    {
        return URL::route(
            $this->routeName,
            [
                'identifier' => (string)$file->getIdentifier(),
            ]
        );
    }
}
