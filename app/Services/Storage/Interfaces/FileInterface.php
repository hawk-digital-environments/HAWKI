<?php
declare(strict_types=1);


namespace App\Services\Storage\Interfaces;


use App\Services\Storage\Value\FileType;
use App\Services\Storage\Value\PlainTextLanguageType;

interface FileInterface extends \Stringable
{
    /**
     * Returns the user facing filename of the stored file, which may differ from the actual filename on disk.
     * @return string
     */
    public function getOriginalFilename(): string;

    /**
     * Returns the ABSOLUTE path on disk where the file is stored, which may differ from the URL used to access the file.
     * @return string
     */
    public function getDiskFilePath(): string;

    /**
     * Returns the type of the stored file, which can be used to determine how to handle the file content.
     * If this returns {@see FileType::PLAIN_TEXT}, the file content can be processed as plain text,
     * where {@see getPlainTextLanguageType()} can be used to determine the language type of the plain text content.
     *
     * @return FileType
     */
    public function getFileType(): FileType;

    /**
     * If the file is a plain text file, this method returns the language type of the plain text content, which can be used to determine how to process the text.
     * If the file is not a plain text file (or we did not recognize the langauge), this method returns null.
     *
     * @return PlainTextLanguageType|null
     */
    public function getPlainTextLanguageType(): PlainTextLanguageType|null;

    /**
     * Returns the MIME type of the stored file, which indicates the type of the file (e.g., "text/plain", "image/jpeg")
     * @return string
     */
    public function getMimeType(): string;

    /**
     * Returns the content of the file as a string. If the file cannot be read, it returns an empty string.
     * @return string
     */
    public function getContent(): string;

    /**
     * Returns a stream resource for reading the file content. If the file cannot be read, it returns null.
     * @return resource|null
     */
    public function getStream(): mixed;

    /**
     * Returns the size of the file in bytes.
     * @return int
     */
    public function getSize(): int;
}
