<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class FileStorageService extends AbstractFileStorage
{
    public function __construct(
        protected array $config,
        protected Filesystem $disk
    )
    {
    }
}
