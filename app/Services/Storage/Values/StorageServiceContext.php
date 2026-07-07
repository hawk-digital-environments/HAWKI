<?php
declare(strict_types=1);


namespace App\Services\Storage\Values;


use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Storage\UrlGenerator;
use App\Services\Storage\Utils\ContentExtractor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;

readonly class StorageServiceContext
{
    public function __construct(
        /**
         * A list of allowed MIME types for file uploads. This is used to limit the mime types that are by default
         * supported by the storage and registered file converter.
         * @var string[]
         */
        public array                $allowedMimeTypes,
        /**
         * The maximum allowed file size for uploads, in bytes.
         * @var int
         */
        public int                  $maxFileSize,
        /**
         * A logger instance for logging storage-related operations and events.
         * @var LoggerInterface
         */
        public LoggerInterface      $logger,
        /**
         * The underlying filesystem disk instance that the storage service will use for file operations. This allows the
         * storage service to interact with the configured filesystem (e.g., local, S3, etc.) for storing and retrieving files.
         * @var Filesystem
         */
        public Filesystem           $filesystem,
        /**
         * The URL generator instance responsible for generating URLs for stored files.
         * @var UrlGenerator
         */
        public UrlGenerator         $urlGenerator,
        /**
         * The content extractor responsible for extracting content from files, such as text or images, using the configured file converter.
         * This allows the storage service to have a consistent way of extracting content from files when needed.
         * @var ContentExtractor
         */
        public ContentExtractor     $contentExtractor,
        /**
         * The attachment database instance used for managing attachments related to stored files.
         * This is required as a legacy support layer, since not all files will have a "metadata" file,
         * so we check the attachment database for any relevant metadata related to the file, such as the original filename.
         * @var AttachmentRepository
         */
        public AttachmentRepository $attachmentRepository,
        /**
         * The clock instance used for time-related operations, such as generating timestamps for stored files. This allows the storage service to have a consistent and testable way of handling time.
         * @var ClockInterface
         */
        public ClockInterface       $clock = new Clock()
    )
    {
    }
}
