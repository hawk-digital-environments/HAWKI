<?php

namespace App\Services\Storage;

use App\Services\Storage\Values\StoredFile;
use Illuminate\Support\Facades\URL;

readonly class UrlGenerator
{
    public function __construct(
        private string $routeName
    )
    {
    }

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
