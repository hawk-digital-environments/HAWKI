<?php
declare(strict_types=1);


namespace App\Services\Storage\Values;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Represents a single derived output produced by the file-converter pipeline for a source file —
 * for example, a Markdown transcript extracted from a PDF.
 *
 * Plain-text files are a special case: instead of spawning a converter, the storage engine creates
 * a virtual extract that points back at the source file itself ({@see self::fromPlainTextFile()}).
 * Non-markdown plain text is dynamically wrapped in a fenced code block when {@see getContent()} is called,
 * so callers always receive consistently formatted text without needing to store a duplicate file.
 *
 * The `diskFolderPath` (source file folder) is not persisted in JSON — it is re-injected when the
 * owning {@see StoredFile} is loaded, mirroring the same design decision made for `StoredFile`.
 */
readonly class StoredFileExtract implements FileInterface, \JsonSerializable
{
    public function __construct(
        /**
         * The name of the folder on disk where the file is stored. This value is not persisted in the json representation,
         * it has to be injected every time the meta is loaded from json, which allows us to move files around on disk without having
         * to update the meta information.
         *
         * Important: This is the path to the folder where the SOURCE file is stored, not the path to the extract file itself.
         * The extract file is always stored in a subfolder of this folder, named according to {@see AbstractFileStorage::EXTRACT_FOLDER_NAME}.
         *
         * @var string
         */
        private string                     $diskFolderPath,
        /**
         * The filename used for storing the file on disk. This is typically provided by the {@see FileConverterInterface::convert()} method when the file
         * is converted, and is used to retrieve the file content from the filesystem. This name is ALWAYS inside the "diskFolderName" folder.
         * This can either be just a filename (e.g., "extract1.txt") or a relative path (e.g., "extracts/extract1.txt"), but it is always relative to the "diskFolderPath".
         * @var string
         */
        private string                     $diskFilePath,
        /**
         * Apart from the mime type, we have this general file categorization which is used for determining how to handle the file in various contexts
         * (e.g., for displaying a preview).
         * @var FileType
         */
        private FileType                   $fileType,
        /**
         * The file extension of the stored file extract, such as "txt", "md", "jpg". This is used for determining the file type and handling it appropriately.
         * @var string
         */
        private string                     $extension,
        /**
         * The MIME type of the stored file extract. This indicates the type of the file (e.g., "text/plain", "image/jpeg")
         * and is used for handling the file appropriately based on its content type.
         * @var string
         */
        private string                     $mimeType,
        /**
         * The size of the stored file extract in bytes. This is used for determining the file size and handling it appropriately,
         * such as for displaying file information or enforcing size limits.
         * @var int
         */
        private int                        $size,
        /**
         * The filesystem instance used to retrieve the file content. This allows the class to access the file content when needed.
         * @var Filesystem
         */
        private Filesystem                 $filesystem,
        /**
         * If "type" maps to {@see FileType::PLAIN_TEXT}, this field indicates the specific type of plain text file, such as "markdown", "code", or "plain".
         * This allows for more granular handling (e.g. syntax highlighting) of plain text files based on their specific format or content type.
         * Consider this ADDITIONAL information, while the "type" field is always the primary categorization of the file, this field provides extra context.
         * @var PlainTextLanguageType|null
         */
        private PlainTextLanguageType|null $plainTextLanguageType = null,
    )
    {
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @inheritDoc
     */
    public function getOriginalFilename(): string
    {
        return basename($this->diskFilePath);
    }

    /**
     * @inheritDoc
     */
    public function getDiskFilePath(): string
    {
        return Path::join($this->diskFolderPath, $this->diskFilePath);
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
     * Get the full disk file path by joining the disk folder path and the disk filename.
     * This is used to retrieve the file content from the filesystem.
     * @return string
     */
    public function getFullDiskFilePath(): string
    {
        return Path::join($this->diskFolderPath, $this->diskFilePath);
    }

    /**
     * @inheritDoc
     */
    public function getStream(): mixed
    {
        return $this->filesystem->readStream($this->getFullDiskFilePath());
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        $content = $this->filesystem->get($this->getFullDiskFilePath());

        if ($this->fileType === FileType::PLAIN_TEXT && $this->plainTextLanguageType !== null) {
            // Special case -> If the language type is markdown, we do not need to wrap the content in markdown syntax, as it is already in markdown format.
            if ($this->plainTextLanguageType === PlainTextLanguageType::MARKDOWN) {
                return $content;
            }

            return self::wrapInMarkdown($this->plainTextLanguageType, $content);
        }

        return $content;
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
            'diskFilename' => $this->diskFilePath,
            'type' => $this->fileType->value,
            'extension' => $this->extension,
            'mimetype' => $this->mimeType,
            'size' => $this->size,
            'languageType' => $this->plainTextLanguageType?->value,
        ];
    }

    /**
     * Factory method to create a StoredFileExtract instance from a JSON string or array. The JSON should contain the necessary metadata for the file extract,
     * such as the disk filename, type, extension, mimetype, size, and optionally the language type for plain text files. The disk folder name and filesystem instance
     * are provided separately to allow for flexibility in handling file storage and retrieval.
     * @param string|array $json
     * @param string $diskFolderName
     * @param Filesystem $filesystem
     * @return self
     * @throws \JsonException
     */
    public static function fromJson(string|array $json, string $diskFolderName, Filesystem $filesystem): self
    {
        $data = is_array($json) ? $json : json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            diskFolderPath: $diskFolderName,
            diskFilePath: $data['diskFilename'],
            fileType: FileType::from($data['type']),
            extension: $data['extension'],
            mimeType: $data['mimetype'],
            size: $data['size'],
            filesystem: $filesystem,
            plainTextLanguageType: isset($data['languageType']) ? PlainTextLanguageType::from($data['languageType']) : null,
        );
    }

    /**
     * Special factory method for creating a StoredFileExtract instance from a plain text file. This method calculates the size of the stored extract by
     * adding the size of the original file content to the additional characters added by wrapping the content in markdown syntax (if applicable).
     * It basically simulates an extraction of a plain text file, where the "extracted" content is the original content wrapped in markdown syntax for
     * proper formatting. This allows for consistent handling of plain text files as extracts, while avoiding to store the same file twice on disk.
     * @param string $storageDiskFilePath
     * @param int $sourceSize
     * @param PlainTextLanguageType $languageType
     * @param Filesystem $filesystem
     * @return self
     */
    public static function fromPlainTextFile(
        string                $storageDiskFilePath,
        int                   $sourceSize,
        PlainTextLanguageType $languageType,
        Filesystem            $filesystem
    ): self
    {
        $size = $sourceSize;

        // Special case -> If the $languageType is markdown, we do not need to wrap the content in markdown syntax.
        if ($languageType !== PlainTextLanguageType::MARKDOWN) {
            $size = strlen(self::wrapInMarkdown($languageType, '')) + $sourceSize;
        }

        return new self(
            diskFolderPath: dirname($storageDiskFilePath),
            diskFilePath: basename($storageDiskFilePath),
            fileType: FileType::PLAIN_TEXT,
            extension: 'md',
            mimeType: 'text/markdown',
            size: $size,
            filesystem: $filesystem,
            plainTextLanguageType: $languageType,
        );
    }

    /**
     * Factory method to create a StoredFileExtract instance from an extracted file. This method calculates the disk folder path and disk file path based on the source file and the extract file,
     * and retrieves the necessary metadata (file type, extension, mimetype, size) from the extract file. This allows for consistent creation of StoredFileExtract instances from extracted files,
     * while ensuring that the correct paths and metadata are set for proper handling and retrieval of the file content.
     * @param string $storageDiskFilePath
     * @param FileReference $extractFile The extracted file containing the content to be stored as a StoredFileExtract. This is used to determine the disk file path and metadata for the extract.
     * @param Filesystem $filesystem The filesystem instance used to retrieve the file content. This allows the class to access the file content when needed.
     * @return self
     */
    public static function fromExtractFile(
        string        $storageDiskFilePath,
        FileReference $extractFile,
        Filesystem    $filesystem
    ): self
    {
        $sourceFolderPath = dirname($storageDiskFilePath);

        return new self(
            diskFolderPath: $sourceFolderPath,
            diskFilePath: Path::makeRelative($extractFile->getDiskFilePath(), $sourceFolderPath),
            fileType: FileType::fromMimeType($extractFile->getMimeType()),
            extension: pathinfo($extractFile->getOriginalFilename(), PATHINFO_EXTENSION),
            mimeType: $extractFile->getMimeType(),
            size: $extractFile->getSize(),
            filesystem: $filesystem,
            plainTextLanguageType: $extractFile->getPlainTextLanguageType()
        );
    }

    /**
     * Wrap the given content in markdown syntax based on the specified plain text language type. This is used for formatting plain text files appropriately
     * when converting them to strings, allowing for proper rendering of markdown, code, or plain text content.
     * @param PlainTextLanguageType $languageType The specific type of plain text file (e.g., markdown, code, plain).
     * @param string $content The raw content of the file to be wrapped in markdown syntax.
     * @return string The content wrapped in markdown syntax based on the specified language type.
     */
    private static function wrapInMarkdown(PlainTextLanguageType $languageType, string $content): string
    {
        return "```{$languageType->value}\n{$content}\n```";
    }
}
