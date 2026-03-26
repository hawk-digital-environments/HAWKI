<?php
declare(strict_types=1);


namespace App\Services\Storage\Value;


use App\Services\Storage\AbstractFileStorage;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\UrlGenerator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

readonly class StoredFile implements FileInterface, \JsonSerializable
{
    /**
     * This is the unique identifier for the stored file.
     * It can be used to reference the file in the storage system, and it is derived from the meta information of the file.
     * @var StoredFileIdentifier
     */
    private StoredFileIdentifier $identifier;

    private function __construct(
        /**
         * A unique identifier for the stored file. This is typically generated when the file is stored and is used to reference the file in the storage system.
         */
        private string                     $uuid,
        /**
         * The category of the stored file, which can be used to group files into different types, for "avatars" or "chat files".
         * @var StoredFileCategory
         */
        private StoredFileCategory         $category,
        /**
         * The size of the stored file in bytes. This is useful for validating file size limits and for displaying file information.
         * This is done once, when the file is stored, to avoid having to read the file contents later just to determine its size.
         * @var int
         */
        private int                        $size,
        /**
         * The timestamp when the file was created or uploaded. This is useful for tracking the age of the file, implementing retention policies,
         * and for displaying file information to users. It is stored as a DateTimeImmutable to ensure that the creation time is not modified after the file is created.
         * @var \DateTimeImmutable
         */
        private \DateTimeImmutable         $createdAt,
        /**
         * If the file has undergone content extraction (e.g., extracting text from a PDF), this field contains a collection of the extracted content.
         * This allows for easy access to the extracted data without needing to know about the underlying file system or extraction process.
         * If this property is NULL, when the file was created the "file converter" was not enabled,
         * If this property contains an empty collection, the file converter was enabled, but no content could be extracted from the file (e.g. an image file).
         * @var FileCollection|null
         */
        private ?FileCollection            $extracts,
        /**
         * The ETag (Entity Tag) of the stored file, which is a hash value that represents the current state of the file. This is used for caching and concurrency control.
         */
        private string                     $etag,
        /**
         * The original filename of the stored file. This is the name of the file as it was uploaded or created,
         * and may differ from the disk filename which is used for storage purposes.
         */
        private string                     $originalFilename,
        /**
         * The name of the folder on disk where the file is stored. This value is not persisted in the json representation,
         * it has to be injected every time the meta is loaded from json, which allows us to move files around on disk without having
         * to update the meta information.
         * @var string
         */
        private string                     $diskFolderPath,
        /**
         * The filename used for storing the file on disk. This is typically a unique identifier or a hashed version
         * of the original filename to avoid conflicts and ensure security.
         * This name is ALWAYS inside the "diskFolderName" folder.
         * @var string
         */
        private string                     $diskFilename,
        /**
         * The MIME type of the stored file. This indicates the type of the file (e.g., "image/jpeg", "application/pdf")
         * and is used for handling the file appropriately based on its content type.
         * @var string
         */
        private string                     $mimeType,
        /**
         * Apart from the mime type, we have this general file categorization which is used for determining how to handle the file in various contexts
         * (e.g., for displaying a preview).
         * @var FileType
         */
        private FileType                   $fileType,
        /**
         * If "type" maps to {@see FileType::PLAIN_TEXT}, this field indicates the specific type of plain text file, such as "markdown", "code", or "plain".
         * This allows for more granular handling (e.g. syntax highlighting) of plain text files based on their specific format or content type.
         * Consider this ADDITIONAL information, while the "type" field is always the primary categorization of the file, this field provides extra context.
         * @var PlainTextLanguageType|null
         */
        private PlainTextLanguageType|null $plainTextLanguageType,
        /**
         * The filesystem instance used to retrieve the file content. This allows the StoredFile to access the file content when needed,
         * such as when converting it to a string or determining its MIME type.
         * @var Filesystem
         */
        private Filesystem                 $filesystem,
        /**
         * The URL generator instance used to generate URLs for the stored file. This allows the StoredFile to provide a way to access the file via a URL,
         * which can be useful for displaying the file in a web application or sharing it with others.
         * @var UrlGenerator
         */
        private UrlGenerator               $urlGenerator
    )
    {
        $this->identifier = StoredFileIdentifier::fromCategoryAndUuid(
            category: $this->category,
            uuid: $this->uuid,
            extension: pathinfo($this->originalFilename, PATHINFO_EXTENSION)
        );
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCategory(): StoredFileCategory
    {
        return $this->category;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExtracts(): ?FileCollection
    {
        return $this->extracts;
    }

    public function getEtag(): string
    {
        return $this->etag;
    }

    /**
     * Returns the identifier of the stored file. This is a unique identifier that is used to identify the file in the storage system.
     * @return StoredFileIdentifier
     */
    public function getIdentifier(): StoredFileIdentifier
    {
        return $this->identifier;
    }

    /**
     * @inheritDoc
     */
    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * @inheritDoc
     */
    public function getDiskFilePath(): string
    {
        return Path::join($this->diskFolderPath, $this->diskFilename);
    }

    /**
     * Returns the path to the meta file of the stored file. This is the file that contains metadata about the stored file.
     * @return string
     */
    public function getMetaDiskFileName(): string
    {
        return Path::join($this->diskFolderPath, AbstractFileStorage::META_FILE_NAME);
    }

    /**
     * @inheritDoc
     */
    public function getFileType(): FileType
    {
        return $this->fileType;
    }

    /**
     * @inheritDoc
     */
    public function getPlainTextLanguageType(): PlainTextLanguageType|null
    {
        return $this->plainTextLanguageType;
    }

    /**
     * @inheritDoc
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @inheritDoc
     */
    public function getStream(): mixed
    {
        return $this->filesystem->readStream($this->getDiskFilePath());
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        return $this->filesystem->get($this->getDiskFilePath()) ?? '';
    }

    /**
     * Returns the filesystem instance used to retrieve the file content.
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Returns the URL to access the stored file. This method uses the UrlGenerator to create a URL based on the file's identifier and meta information.
     * @return string
     */
    public function getUrl(): string
    {
        return $this->urlGenerator->generate($this);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getContent();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'category' => $this->category->value,
            'filename' => $this->originalFilename,
            'diskFilename' => $this->diskFilename,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'type' => $this->fileType->value,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'plainTextLanguageType' => $this->plainTextLanguageType?->value,
            'extracts' => $this->extracts,
            'etag' => $this->etag,
        ];
    }

    /**
     * Creates an instance of StoredFile from a JSON string representation of its metadata.
     * The JSON string should contain all the necessary information to reconstruct the StoredFileMeta,
     * and the diskFolderPath and filesystem are used to properly initialize the meta information.
     *
     * A word on the "diskFolderPath" parameter: We want to move the files around on disk without having to update the
     * meta information, so we inject the "diskFolderPath" every time we load the meta from json,
     * instead of persisting it in the json representation. This way the paths are always relative
     * to the current disk folder structure.
     */
    public static function fromMetaJson(
        string       $json,
        string       $diskFolderPath,
        Filesystem   $filesystem,
        UrlGenerator $urlGenerator
    ): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self(
            uuid: $data['uuid'],
            category: StoredFileCategory::from($data['category']),
            size: $data['size'],
            createdAt: new \DateTimeImmutable($data['createdAt']),
            extracts: is_array($data['extracts'] ?? null)
                ? new FileCollection(...array_map(
                    static fn(array $extractData) => StoredFileExtract::fromJson($extractData, $diskFolderPath, $filesystem),
                    $data['extracts']
                ))
                : null,
            etag: $data['etag'],
            originalFilename: $data['filename'],
            diskFolderPath: $diskFolderPath,
            diskFilename: $data['diskFilename'],
            mimeType: $data['mimeType'],
            fileType: FileType::from($data['type']),
            plainTextLanguageType: PlainTextLanguageType::tryFrom($data['plainTextLanguageType'] ?? 'nope'),
            filesystem: $filesystem,
            urlGenerator: $urlGenerator
        );
    }

    /**
     * Factory method to create a new instance of StoredFile for a newly stored file. This method takes the necessary information about the file,
     * such as its identifier, filesystem reference, extracts, and creation time, and constructs a new StoredFile instance with the appropriate metadata and properties.
     * The ETag is generated based on the creation time and file size to provide a unique identifier for the file's state, which can be used for caching and concurrency control.
     */
    public static function fromNewFile(
        string               $storageDiskFilePath,
        StoredFileIdentifier $identifier,
        FileReference        $filesystemFile,
        FileCollection       $extracts,
        Filesystem           $filesystem,
        UrlGenerator         $urlGenerator,
        \DateTimeImmutable   $createdAt,
    ): self
    {
        return new self(
            uuid: $identifier->uuid,
            category: $identifier->category,
            size: $filesystemFile->getSize(),
            createdAt: $createdAt,
            extracts: $extracts,
            etag: md5($createdAt->getTimestamp() . '-' . $filesystemFile->getSize()),
            originalFilename: $filesystemFile->getOriginalFilename(),
            diskFolderPath: dirname($storageDiskFilePath),
            diskFilename: basename($storageDiskFilePath),
            mimeType: $filesystemFile->getMimeType(),
            fileType: $filesystemFile->getFileType(),
            plainTextLanguageType: $filesystemFile->getPlainTextLanguageType(),
            filesystem: $filesystem,
            urlGenerator: $urlGenerator
        );
    }
}
