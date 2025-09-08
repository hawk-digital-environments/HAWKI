<?php

namespace App\Services\Storage;


use Illuminate\Contracts\Filesystem\Filesystem;

class AvatarStorageService extends AbstractFileStorage
{

    public function __construct(
        protected Filesystem $disk
    )
    {
    }
}


