<?php

namespace App\Services\Storage;

use App\Services\Storage\Value\StorageFileInfo;
use Illuminate\Support\Facades\URL;

readonly class UrlGenerator
{
    public function __construct(
        private string $routeName
    )
    {
    }
    
    public function generate(StorageFileInfo $info): string
    {
        return URL::route(
            $this->routeName,
            [
                'category' => $info->category,
                'filename' => $info->basename,
            ]
        );
    }
}
