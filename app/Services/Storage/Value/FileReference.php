<?php
declare(strict_types=1);


namespace App\Services\Storage\Value;


use App\Services\Storage\Interfaces\FileInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

/**
 * Represents a reference to a file that may reside in one of three locations:
 * - In memory (raw string content)
 * - On the local disk (an absolute file system path without a Laravel Filesystem instance)
 * - On a Laravel Filesystem disk (e.g. S3, local storage disk)
 *
 * Use the static factory methods to create instances:
 * {@see self::fromContent()}, {@see self::fromDisk()}, {@see self::fromFilesystemDisk()},
 * {@see self::fromUploadedFile()}, {@see self::fromStoredFile()}
 */
class FileReference implements \Stringable, FileInterface
{
    private int $size;
    private string $mimeType;
    private bool $isPlainText;
    private FileType $mediaType;
    private PlainTextLanguageType|null $plainTextLanguageType;
    private bool $plainTextLangaugeTypeDetermined = false;
    private string $temporaryLocalFilePath;

    private function __construct(
        private readonly string          $originalFileName,
        private readonly string|null     $diskFilePath,
        private readonly string|null     $content,
        private readonly Filesystem|null $filesystem = null
    )
    {
    }

    /**
     * Returns true if the file exists on any disk (local or filesystem).
     */
    public function existsOnDisk(): bool
    {
        return $this->existsOnLocalDisk() || $this->existsOnFilesystemDisk();
    }

    /**
     * Returns true if this is a local file reference and the file exists on the local filesystem.
     */
    public function existsOnLocalDisk(): bool
    {
        return $this->isLocalFile() && file_exists($this->diskFilePath);
    }

    /**
     * Returns true if this is a Laravel Filesystem file reference and the file exists on that disk.
     */
    public function existsOnFilesystemDisk(): bool
    {
        return $this->isFilesystemFile() && $this->filesystem->exists($this->diskFilePath);
    }

    /**
     * Returns true if the file content is held in memory rather than on disk.
     */
    public function isInMemory(): bool
    {
        return $this->content !== null;
    }

    /**
     * Returns true if the file is backed by a plain local filesystem path
     * (i.e. not in memory and not managed by a Laravel Filesystem instance).
     */
    public function isLocalFile(): bool
    {
        return !$this->isInMemory() && $this->filesystem === null && $this->diskFilePath !== null;
    }

    /**
     * Returns true if the file is managed by a Laravel Filesystem instance (e.g. S3, local storage disk).
     */
    public function isFilesystemFile(): bool
    {
        return !$this->isInMemory() && $this->filesystem !== null && $this->diskFilePath !== null;
    }

    /**
     * Returns the full file content as a string.
     * Reads from memory, the Laravel Filesystem disk, or the local disk — in that order of precedence.
     * Returns an empty string if the content cannot be retrieved.
     */
    public function getContent(): string
    {
        if ($this->isInMemory()) {
            return $this->content;
        }

        if ($this->existsOnFilesystemDisk()) {
            return $this->filesystem->get($this->diskFilePath) ?? '';
        }

        if ($this->existsOnDisk()) {
            return file_get_contents($this->diskFilePath) . '';
        }

        return '';
    }

    /**
     * Returns the file content as a readable stream resource.
     * Reads from memory, the Laravel Filesystem disk, or the local disk — in that order of precedence.
     * Returns null if the content cannot be streamed.
     */
    public function getStream(): mixed
    {
        if ($this->isInMemory()) {
            return Utils::streamFor($this->content)->detach();
        }

        if ($this->existsOnFilesystemDisk()) {
            return $this->filesystem->readStream($this->diskFilePath);
        }

        if ($this->existsOnLocalDisk()) {
            return fopen($this->diskFilePath, 'rb');
        }

        return null;
    }

    /**
     * Returns the disk file path for this file reference, if it exists.
     * For Laravel Filesystem files, this is the path on the filesystem disk; for local files, this is the absolute local file path.
     * @return string
     */
    public function getDiskFilePath(): string
    {
        if (!empty($this->diskFilePath)) {
            return $this->diskFilePath;
        }
        return $this->getLocalFilePath();
    }

    /**
     * Returns an absolute path on the local filesystem for this file.
     *
     * For local files the stored path is returned directly. For in-memory or
     * Laravel Filesystem files, the content is written to a temporary file on
     * the first call and the temporary path is cached for subsequent calls.
     * The temporary file preserves the original file extension.
     */
    public function getLocalFilePath(): string
    {
        if ($this->isLocalFile()) {
            return $this->diskFilePath;
        }

        if (isset($this->temporaryLocalFilePath)) {
            return $this->temporaryLocalFilePath;
        }

        $extension = pathinfo($this->originalFileName, PATHINFO_EXTENSION);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'secure_file') . '.' . $extension;
        if ($this->isFilesystemFile()) {
            file_put_contents($temporaryPath, $this->filesystem->readStream($this->diskFilePath));
        } else if ($this->isInMemory()) {
            file_put_contents($temporaryPath, $this->content);
        }

        return $this->temporaryLocalFilePath = $temporaryPath;
    }

    /**
     * Returns the original filename as provided when the reference was created
     * (e.g. the client-supplied name for an uploaded file).
     */
    public function getOriginalFilename(): string
    {
        return $this->originalFileName;
    }

    /**
     * Return the MIME type of the file, or null if it cannot be determined.
     * Uses the fileinfo extension to analyze the file content rather than relying on the filename extension.
     *
     * @return string
     */
    public function getMimeType(): string
    {
        if (isset($this->mimeType)) {
            return $this->mimeType;
        }

        $info = (new \finfo(FILEINFO_MIME_TYPE));
        $mimeType = false;

        if ($this->isInMemory()) {
            $mimeType = $info->buffer($this->content);
        } else if ($this->isLocalFile()) {
            $mimeType = $info->file($this->diskFilePath);
        } else if ($this->isFilesystemFile()) {
            $mimeType = $info->file($this->getLocalFilePath());
        }

        // Special handling for PDF files, which could also be Illustrator files
        if ($mimeType === 'application/pdf') {
            $extension = pathinfo($this->originalFileName, PATHINFO_EXTENSION);
            if (strtolower($extension) === 'ai') {
                $mimeType = 'application/illustrator';
            }
        }

        return $this->mimeType = ($mimeType === false ? 'application/octet-stream' : $mimeType);
    }

    /**
     * Get the size of the file in bytes.
     * @return int
     */
    public function getSize(): int
    {
        if (isset($this->size)) {
            return $this->size;
        }

        if ($this->isInMemory()) {
            return $this->size = strlen($this->content ?? '');
        }

        if ($this->existsOnLocalDisk()) {
            return $this->size = filesize($this->diskFilePath);
        }

        if ($this->existsOnFilesystemDisk()) {
            return $this->size = $this->filesystem->size($this->diskFilePath);
        }

        return $this->size = 0;
    }

    /**
     * Determine if the file is a plain text file.
     * This method checks for null bytes and validates the encoding of the content.
     * It does not rely on the file extension or MIME type, as these can be easily spoofed.
     *
     * @return bool
     */
    public function isPlainTextFile(): bool
    {
        if (isset($this->isPlainText)) {
            return $this->isPlainText;
        }

        if ($this->isInMemory()) {
            $sample = substr($this->content, 0, 8192);
        } else if ($this->isLocalFile() || $this->isFilesystemFile()) {
            $handle = fopen($this->getLocalFilePath(), 'rb');
            if ($handle === false) {
                return false;
            }

            $sample = fread($handle, 8192);
            fclose($handle);
        } else {
            return $this->isPlainText = false;
        }

        // Empty or unreadable files are not considered plain text
        if ($sample === false || $sample === '') {
            return $this->isPlainText = false;
        }

        // Check for null bytes, which are a strong indicator of binary files. While
        // some text files could technically contain null bytes, it's uncommon.
        if (str_contains($sample, "\0")) {
            return $this->isPlainText = false;
        }

        // Check if the encoding is valid for common text encodings. If the encoding cannot be detected or is not a common text encoding, it's likely a binary file.
        $foundEncoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($foundEncoding === false) {
            return $this->isPlainText = false;
        }

        // This is the part where it gets a bit tricky.
        // For example, PDF files can contain a lot of binary data but also have a lot of printable characters,
        // so we double-check our internal plain text language type detection. If the mime type
        // indicates a plain text file, too, we can be pretty confident that it's not a binary file with a lot of printable characters.
        if (PlainTextLanguageType::tryFromMimetype($this->getMimeType()) === null) {
            return $this->isPlainText = false;
        }

        // At this point, this is as confident we can be that this is a plain text file based on the content analysis, so we return true.
        return true;
    }

    /**
     * Apart from the mime type, we have this general file categorization which is used for determining how to handle the file in various contexts
     *  (e.g., for displaying a preview). This method determines the media type of the file based on its content and MIME type, rather
     * than relying solely on the file extension or MIME type, as these can be easily spoofed.
     */
    public function getFileType(): FileType
    {
        if (isset($this->mediaType)) {
            return $this->mediaType;
        }

        if ($this->isPlainTextFile()) {
            return $this->mediaType = FileType::PLAIN_TEXT;
        }

        return $this->mediaType = FileType::fromMimeType($this->getMimeType());
    }

    /**
     * If "type" maps to {@see FileType::PLAIN_TEXT}, this method indicates the specific type of plain text file, such as "markdown", "js", "python", or "plain".
     * This allows for more granular handling (e.g. syntax highlighting) of plain text files based on their specific format or content type.
     * This method determines the plain text language type based on the file content and MIME type, rather than relying solely on the file extension or MIME type,
     * as these can be easily spoofed.
     * @return PlainTextLanguageType|null
     */
    public function getPlainTextLanguageType(): PlainTextLanguageType|null
    {
        if ($this->plainTextLangaugeTypeDetermined) {
            return $this->plainTextLanguageType;
        }

        $this->plainTextLanguageType = PlainTextLanguageType::tryFromFilename($this->filename ?? '')
            ?? PlainTextLanguageType::tryFromMimeType($this->getMimeType());

        $this->plainTextLangaugeTypeDetermined = true;

        return $this->plainTextLanguageType;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getContent();
    }

    /**
     * Returns a new FileReference instance with the same content and disk path but a different original filename.
     * This allows effective "renaming" of the file reference without modifying the underlying file or its content, which can be useful for
     * correcting mislabeled files or providing more descriptive names for extracted content.
     */
    public function withOriginalFilename(string $originalFilename): self
    {
        return new self(
            $originalFilename,
            $this->diskFilePath,
            $this->content,
            $this->filesystem
        );
    }

    /**
     * Creates a FileReference from a {@see StoredFile} or {@see StoredFileExtract} instance,
     * backed by the file's Filesystem disk and disk path.
     */
    public static function fromStoredFile(StoredFile|StoredFileExtract $file): self
    {
        return new self(
            $file->getOriginalFilename(),
            $file->getDiskFilePath(),
            null,
            $file->getFilesystem()
        );
    }

    /**
     * Creates a FileReference from a Laravel {@see UploadedFile} (e.g. from an HTTP file upload).
     * The reference points to the temporary local file path where the upload was stored.
     */
    public static function fromUploadedFile(UploadedFile $u): self
    {
        return new self($u->getClientOriginalName(), $u->getPathname(), null);
    }

    /**
     * Creates an in-memory FileReference from a raw string content and a filename.
     * No disk I/O occurs until the content is explicitly read or a disk path is requested.
     */
    public static function fromContent(
        string $originalFilename,
        string $content
    ): self
    {
        return new self($originalFilename, null, $content);
    }

    /**
     * Creates a FileReference pointing to a file on the local filesystem.
     * If no original filename is provided, the basename of the path is used.
     */
    public static function fromDisk(
        string      $diskFilePath,
        string|null $originalFilename = null
    ): self
    {
        if (empty($originalFilename)) {
            $originalFilename = basename($diskFilePath);
        }

        return new self($originalFilename, $diskFilePath, null);
    }

    /**
     * Creates a FileReference pointing to a file managed by a Laravel Filesystem disk (e.g. S3 or a named local disk).
     * If no original filename is provided, the basename of the path is used.
     */
    public static function fromFilesystemDisk(
        string      $diskFilePath,
        Filesystem  $filesystem,
        string|null $originalFilename = null
    ): self
    {
        if (empty($originalFilename)) {
            $originalFilename = basename($diskFilePath);
        }

        return new self($originalFilename, $diskFilePath, null, $filesystem);
    }
}
