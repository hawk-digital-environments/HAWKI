<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\PlainTextLanguageType;
use App\Services\Storage\Values\StorageServiceContext;
use Symfony\Component\Mime\MimeTypes;

/**
 * @api
 *
 * Storage service for general file uploads — chat-room attachments and private conversation files.
 *
 * Accepted formats are the union of:
 * - Common image formats (PNG, JPEG, GIF)
 * - All plain-text / source-code file types known to {@see PlainTextLanguageType}
 * - Any format the configured {@see FileConverterInterface} can process (e.g. PDF, Word documents)
 *
 * An admin-configured allow-list ({@see StorageServiceContext::$allowedMimeTypes}) can further
 * restrict which types are accepted; see {@see AbstractFileStorage::filterMimeTypesByAllowed()}.
 *
 * Content extraction runs for every stored file (the default behaviour inherited from
 * {@see AbstractFileStorage}), so that AI models can read document contents as text.
 */
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
