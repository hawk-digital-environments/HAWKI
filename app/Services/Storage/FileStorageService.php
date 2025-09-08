<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

class FileStorageService extends AbstractFileStorage
{
    public function __construct(
        protected Filesystem $disk
    )
    {
    }
}
