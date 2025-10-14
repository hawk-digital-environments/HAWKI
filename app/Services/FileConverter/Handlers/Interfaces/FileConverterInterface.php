<?php

namespace App\Services\FileConverter\Handlers\Interfaces;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\SplFileInfo;

interface FileConverterInterface
{

    public function convert(UploadedFile|SplFileInfo|string $file): array;



}
