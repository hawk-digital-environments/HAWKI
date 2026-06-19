<?php

namespace App\Services\Storage;

use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\PlainTextLanguageType;
use App\Services\Storage\Values\StorageServiceContext;
use Symfony\Component\Mime\MimeTypes;

class FileStorageService extends AbstractFileStorage
{
    public function __construct(
        StorageServiceContext                   $context,
        private readonly FileConverterInterface $fileConverter
    )
    {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        $mime = new MimeTypes();

        return $this->filterMimeTypesByAllowed(
            array_merge(
                $mime->getMimeTypes('png'),
                $mime->getMimeTypes('jpeg'),
                $mime->getMimeTypes('jpg'),
                $mime->getMimeTypes('gif'),
                PlainTextLanguageType::getMimeTypes(),
                $this->fileConverter->getAllowedMimeTypes()
            )
        );
    }
}
