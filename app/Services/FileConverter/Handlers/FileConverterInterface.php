<?php

namespace App\Services\FileConverter\Handlers;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\SplFileInfo;

interface FileConverterInterface
{
    /**
     * Receives any kind of file input (UploadedFile, SplFileInfo, or string content) and returns an array of extracted contents.
     * The resulting array is a list of file names as keys and their corresponding extracted content as values. For example:
     * [
     *     'document.md' => '# Extracted Markdown Content\nThis is the content extracted from the file.',
     *     'summary.txt' => 'This is a plain text summary of the document.'
     * ]
     * @param UploadedFile|SplFileInfo|string $file
     * @return array<string, string> An associative array where keys are file names and values are the extracted content.
     */
    public function convert(UploadedFile|SplFileInfo|string $file): array;

    /**
     * Returns true if the converter is available and can be used, false otherwise.
     * @return bool
     */
    public function isAvailable(): bool;

}
