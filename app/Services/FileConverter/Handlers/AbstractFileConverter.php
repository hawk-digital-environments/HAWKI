<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\FileReference;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Base class for file converters, providing shared helpers for opening file streams
 * and resolving file names across the three supported input types:
 * {@see UploadedFile}, {@see SplFileInfo}, and {@see FileReference}.
 *
 * Concrete implementations must provide {@see FileConverterInterface::isValidConfig()},
 * {@see FileConverterInterface::convert()}, {@see FileConverterInterface::isAvailable()},
 * and {@see FileConverterInterface::getAllowedMimeTypes()}.
 */
abstract class AbstractFileConverter implements FileConverterInterface
{
    /**
     * Converter configuration supplied via {@see setConfig()}.
     * The expected keys and their meaning are defined by each concrete implementation.
     */
    protected array $config;

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function canConvertMimetype(string $mimetype): bool
    {
        $mimeTypes = $this->getAllowedMimeTypes();
        return in_array($mimetype, $mimeTypes, true);
    }

    /**
     * @inheritDoc
     */
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool
    {
        return false;
    }

    /**
     * Resolves the HTTP timeout (in seconds) applied to the converter's main conversion request.
     *
     * Returns the per-converter `timeout` config value when present, otherwise falls back to
     * the 60-second default.
     */
    protected function getRequestTimeout(): int
    {
        return isset($this->config['timeout']) ? (int) $this->config['timeout'] : 60;
    }
}
