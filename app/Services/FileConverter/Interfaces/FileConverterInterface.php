<?php
declare(strict_types=1);

namespace App\Services\FileConverter\Interfaces;

use App\Services\FileConverter\Handlers\KreuzbergConverter;
use App\Services\FileConverter\Utils\ImagePreProcessingConverter;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;

/**
 * Contract for document converters that extract structured content from binary files.
 *
 * Each converter connects to an external service or CLI tool. The active converter is assembled
 * and bound by {@see \App\Providers\FileConverterServiceProvider}, which always wraps the chosen
 * implementation with {@see ImagePreProcessingConverter} to pre-process exotic image formats
 * (SVG, TIFF, PSD, EPS, …) before the underlying converter sees them.
 *
 * Typical usage via the bound singleton:
 * ```php
 * // Inject via constructor:
 * public function __construct(private readonly FileConverterInterface $converter) {}
 *
 * // Then use it:
 * if ($this->converter->isAvailable() && $this->converter->canConvertMimetype($file->getMimeType())) {
 *     $extracts = $this->converter->convert($file); // FileCollection of extracted artefacts
 * }
 * ```
 *
 * @see \App\Providers\FileConverterServiceProvider  assembles and binds the converter pipeline
 * @see \App\Services\Storage\Utils\ContentExtractor primary consumer
 */
interface FileConverterInterface
{
    /**
     * Returns true when the given configuration array contains all keys required by this converter.
     * Called by {@see \App\Providers\FileConverterServiceProvider} before instantiating a converter
     * to skip candidates whose environment variables are not set.
     */
    public static function isValidConfig(array $config): bool;

    /**
     * Injects the resolved configuration array into this converter instance.
     * Called by the service provider immediately after construction.
     */
    public function setConfig(array $config): void;

    /**
     * Extracts all content from the given file and returns the results as a collection of
     * {@see FileReference} objects. Depending on the file type and the converter, results
     * typically include a Markdown text file and one or more image files.
     *
     * @throws \App\Services\FileConverter\Exception\ConversionFailedException on any extraction error.
     */
    public function convert(FileReference $file): FileCollection;

    /**
     * Returns true when the converter's backing service or binary is reachable and ready.
     * {@see \App\Providers\FileConverterServiceProvider} skips converters that return false here.
     */
    public function isAvailable(): bool;

    /**
     * Returns the list of MIME types this converter can process.
     * Used by {@see canConvertMimetype()} and by callers deciding whether to invoke the converter.
     *
     * @return string[] Lowercase MIME type strings, e.g. `['application/pdf', 'image/png']`.
     */
    public function getAllowedMimeTypes(): array;

    /**
     * Returns true when the given MIME type is in the list returned by {@see getAllowedMimeTypes()}.
     */
    public function canConvertMimetype(string $mimetype): bool;

    /**
     * Signals that this converter prefers another converter to handle the given MIME type,
     * even though it technically supports it.
     *
     * For example, {@see KreuzbergConverter} can convert SVG but only extracts text — it returns
     * true here for `image/svg+xml` so the wrapping {@see ImagePreProcessingConverter} can intercept
     * the file and produce a proper PNG rendering instead. If no better handler is available the
     * original converter will still be used as a fallback.
     */
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool;
}
