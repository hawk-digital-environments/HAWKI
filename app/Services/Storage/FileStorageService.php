<?php

namespace App\Services\Storage;

use Symfony\Component\Mime\MimeTypes;

class FileStorageService extends AbstractFileStorage
{
    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        if (!empty($this->allowedMimeTypes)) {
            return $this->allowedMimeTypes;
        }
        
        $mime = new MimeTypes();
        
        return array_merge(
            $mime->getMimeTypes('png'),
            $mime->getMimeTypes('jpeg'),
            $mime->getMimeTypes('jpg'),
            $mime->getMimeTypes('gif'),
            $mime->getMimeTypes('bmp'),
            $mime->getMimeTypes('tiff'),
            $mime->getMimeTypes('pdf'),
            $mime->getMimeTypes('doc'),
            $mime->getMimeTypes('docx'),
        );
    }
}
