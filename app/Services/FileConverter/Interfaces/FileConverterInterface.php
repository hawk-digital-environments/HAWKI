<?php

namespace App\Services\FileConverter\Interfaces;

use App\Services\FileConverter\Handlers\KreuzbergConverter;
use App\Services\FileConverter\Utils\ImagePreProcessingConverter;
use App\Services\Storage\Value\FileCollection;
use App\Services\Storage\Value\FileReference;

interface FileConverterInterface
{
    /**
     * Validates the provided configuration array for the converter.
     * The specific validation rules will depend on the implementation of the converter.
     * For example, it may check for required keys, value types, or value ranges.
     *
     * @param array $config
     * @return bool Returns true if the configuration is valid, false otherwise.
     */
    public static function isValidConfig(array $config): bool;

    /**
     * Allows the outside caller to set configuration options for the converter.
     * The specific configuration options will depend on the implementation of the converter.
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * Receives a File Reference as input, extracts all possible content from it, and returns a collection of
     * {@see FileReference} objects representing the extracted content. The specific extraction process will depend on
     * the implementation of the converter and the type of file being processed.
     * @param FileReference $file
     * @return FileCollection
     */
    public function convert(FileReference $file): FileCollection;

    /**
     * Returns true if the converter is available and can be used, false otherwise.
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the list of allowed MIME types supported by this converter
     *
     * @return array The list of allowed MIME types
     */
    public function getAllowedMimeTypes(): array;

    /**
     * Checks if the converter can extract content from the given MIME type.
     *
     * @param string $mimetype The MIME type to check.
     * @return bool True if the converter can extract content from the given MIME type, false otherwise.
     */
    public function canConvertMimetype(string $mimetype): bool;

    /**
     * This method can be implemented if your converter is technically able to handle a filetype,
     * but, if there are other, better solutions available, they should handle the conversion.
     *
     * Why this? For example the {@see KreuzbergConverter} can technically convert svg files, but
     * the result is, lets say "meh". It only extracts the text content but does not provide an image
     * for the AI to see. Our internal {@see ImagePreProcessingConverter} does a much better job to handle
     * svgs but requires a dedicated command line tool on the server. So, if a svg is presented, we first ask
     * check if our command line tool is enabled, if so we ask the real converter,
     * "would you like someone else to handle this?". If it agrees the image preprocessor will handle it,
     * otherwise if the command line tool is not installed, Kreuzberg will always handle svgs in its own way.
     *
     * @param string $mimetype
     * @return bool
     */
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool;
}
