<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Utils;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Value\FileCollection;
use App\Services\Storage\Value\FileReference;
use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Wraps any {@see FileConverterInterface} implementation with local image pre-processing.
 *
 * Some document conversion services cannot handle every image format directly (e.g. SVG,
 * multi-page TIFF, PSD, EPS). This converter intercepts those file types, converts them
 * locally using lightweight CLI tools, and then hands the resulting standard image(s) off
 * to the inner concrete converter for the actual text/content extraction.
 *
 * Two external binaries are used and detected at runtime:
 *   - rsvg-convert  (package librsvg2-bin)  – SVG → PNG
 *   - ImageMagick convert (package imagemagick) – TIFF, PSD, EPS, AI, BMP, ICO, … → JPEG
 *
 * Both binary paths are configurable via {@see \App\Providers\FileConverterServiceProvider}
 * and the `file_converter.binaries` config key.
 */
readonly class ImagePreProcessingConverter implements FileConverterInterface
{
    private array $imagickMimeTypes;

    public function __construct(
        private FileConverterInterface $concreteConverter,
        private LoggerInterface        $logger,
        private Repository             $cache,
        private string                 $rsvgConvertBinary = 'rsvg-convert',
        private string                 $imageMagickBinary = 'convert',
    )
    {
        $mime = new MimeTypes();
        $this->imagickMimeTypes = array_merge(
            $mime->getMimeTypes('ai'),
            $mime->getMimeTypes('eps'),
            $mime->getMimeTypes('ps'),
            $mime->getMimeTypes('psd'),
            $mime->getMimeTypes('tiff'),
            $mime->getMimeTypes('tif'),
            $mime->getMimeTypes('bmp'),
            $mime->getMimeTypes('ico'),
        );
    }

    /**
     * Returns true when the rsvg-convert binary is available on the server.
     *
     * The result is cached for 24 hours to avoid repeated subprocess calls on
     * every request. Clear the application cache after installing or removing
     * the binary to force re-detection.
     */
    public function canConvertSvg(): bool
    {
        return $this->cache->remember('common-image-format-extractor.canConvertSvg', 60 * 60 * 24, function () {
            // Check if rsvg-convert is installed on the machine
            $found = exec('which ' . escapeshellarg($this->rsvgConvertBinary)) !== '';
            $this->logger->debug('SVG conversion tool found: ' . ($found ? 'yes' : 'no'));
            return $found;
        });
    }

    /**
     * Returns true when the ImageMagick CLI binary (typically `convert`) is available on the server.
     *
     * The result is cached for 24 hours to avoid repeated subprocess calls on
     * every request. Clear the application cache after installing or removing
     * the binary to force re-detection.
     */
    public function canConvertWithImagick(): bool
    {
        return $this->cache->remember('common-image-format-extractor.hasImagemagickCli', 60 * 60 * 24, function () {
            $found = exec('which ' . escapeshellarg($this->imageMagickBinary)) !== '';
            $this->logger->debug('ImageMagick CLI conversion tool found: ' . ($found ? 'yes' : 'no'));
            return $found;
        });
    }

    /**
     * @inheritDoc
     */
    public static function isValidConfig(array $config): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
        $this->concreteConverter->setConfig($config);
    }

    /**
     * @inheritDoc
     */
    public function convert(FileReference $file): FileCollection
    {
        $converterIsAvailable = $this->concreteConverter->isAvailable();
        $converterCanHandleFile = $converterIsAvailable && $this->concreteConverter->canConvertMimetype($file->getMimeType());
        $converterWantsToHandleFile = $converterCanHandleFile && !$this->concreteConverter->wouldLikeSomeoneElseToConvertMimetype($file->getMimeType());
        $shouldUsePreProcessor = (!$converterIsAvailable || !$converterCanHandleFile || !$converterWantsToHandleFile);

        if ($shouldUsePreProcessor
            && $this->canConvertSvg()
            && $file->getMimeType() === 'image/svg+xml'
        ) {
            return $this->convertSvg($file);
        }

        if ($shouldUsePreProcessor
            && $this->canConvertWithImagick()
            && in_array($file->getMimeType(), $this->imagickMimeTypes, true)
        ) {
            return $this->convertWithImagick($file);
        }

        if ($converterIsAvailable && $converterCanHandleFile) {
            return $this->concreteConverter->convert($file);
        }

        return new FileCollection();
    }

    /**
     * Converts an SVG file to PNG via rsvg-convert, then optionally passes the PNG
     * to the concrete converter if it supports image/png.
     *
     * Both the intermediate SVG and PNG temp files are cleaned up on shutdown.
     */
    private function convertSvg(FileReference $file): FileCollection
    {
        logFile($file->getDiskFilePath());
        $svgPath = sys_get_temp_dir() . '/' . uniqid('source_', true) . '.svg';
        file_put_contents($svgPath, $file->getStream());
        $pngPath = sys_get_temp_dir() . '/' . uniqid('converted_', true) . '.png';

        // Automatically clean up the temporary files after the request is complete
        register_shutdown_function(static function () use ($svgPath, $pngPath) {
            @unlink($svgPath);
            @unlink($pngPath);
        });

        $result = exec(
            escapeshellarg($this->rsvgConvertBinary) . ' -u -f png -o ' . escapeshellarg($pngPath) . ' ' . escapeshellarg($svgPath),
            $output,
            $returnVar
        );

        if ($returnVar !== 0) {
            $this->logger->error('Failed to convert SVG to JPG', [
                'svgPath' => $svgPath,
                'pngPath' => $pngPath,
                'output' => $output,
                'result' => $result,
                'returnVar' => $returnVar,
            ]);
        }

        $filenameWithoutExt = pathinfo($file->getOriginalFilename(), PATHINFO_FILENAME);
        $extractFilename = $filenameWithoutExt . '.png';
        $pngRef = FileReference::fromDisk($pngPath, $extractFilename);
        $results = [$pngRef];

        if (!$pngRef->existsOnLocalDisk() || $pngRef->getSize() === 0) {
            $this->logger->error('Failed to convert SVG to PNG, output file does not exist or is empty', [
                'svgPath' => $svgPath,
                'pngPath' => $pngPath,
                'output' => $output,
                'result' => $result,
                'returnVar' => $returnVar,
            ]);
            return new FileCollection();
        }

        // If our concrete converter can handle JPG, we can try to convert it further
        if ($this->concreteConverter->isAvailable() && $this->concreteConverter->canConvertMimetype('image/png')) {
            $results[] = $this->concreteConverter->convert($pngRef);
        }

        return new FileCollection(...$results);
    }

    /**
     * Converts exotic or multi-page image formats (TIFF, PSD, EPS, AI, BMP, ICO, …) to JPEG
     * via the ImageMagick CLI, producing one JPEG file per page/frame.
     *
     * ImageMagick's `%d` output-name placeholder is used so that a single `convert` call
     * yields all pages at once (e.g. `document-0.jpg`, `document-1.jpg`, …). Each resulting
     * JPEG is optionally forwarded to the concrete converter if it supports image/jpeg.
     *
     * All temporary input and output files are cleaned up on shutdown.
     */
    private function convertWithImagick(FileReference $file): FileCollection
    {
        // Write the source to a temp file so the CLI tool can read it.
        $ext = pathinfo($file->getOriginalFilename(), PATHINFO_EXTENSION) ?: 'bin';
        $inputPath = sys_get_temp_dir() . '/' . uniqid('imagick_in_', true) . '.' . $ext;
        file_put_contents($inputPath, $file->getContent());

        // ImageMagick expands %d in the output path to the page index (0-based),
        // producing one file per page. Single-page documents still produce -0.jpg.
        $outputPrefix = sys_get_temp_dir() . '/' . uniqid('imagick_out_', true);
        $outputPattern = $outputPrefix . '-%d.jpg';

        $cmd = escapeshellarg($this->imageMagickBinary)
            . ' ' . escapeshellarg($inputPath)
            . ' ' . escapeshellarg($outputPattern);

        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->logger->error('Failed to convert file with ImageMagick CLI', [
                'inputPath' => $inputPath,
                'outputPattern' => $outputPattern,
                'output' => $output,
                'returnVar' => $returnVar,
            ]);
            @unlink($inputPath);
            return new FileCollection();
        }

        // Collect all page files; natsort keeps multi-page documents in order (page 9 before page 10).
        $outputFiles = glob($outputPrefix . '-*.jpg') ?: [];
        natsort($outputFiles);

        if (empty($outputFiles)) {
            $this->logger->error('ImageMagick CLI produced no output files', [
                'inputPath' => $inputPath,
                'outputPattern' => $outputPattern,
                'output' => $output,
            ]);
            @unlink($inputPath);
            return new FileCollection();
        }

        $filesToCleanUp = [$inputPath, ...$outputFiles];
        $filenameWithoutExt = pathinfo($file->getOriginalFilename(), PATHINFO_FILENAME);
        $results = [];
        $idx = 0;
        foreach ($outputFiles as $outputFile) {
            $extractFilename = $filenameWithoutExt . '-' . $idx . '.jpg';
            $idx++;
            $jpgRef = FileReference::fromDisk($outputFile, $extractFilename);
            $results[] = $jpgRef;

            // If our concrete converter can handle JPG, we can try to convert it further
            if ($this->concreteConverter->isAvailable() && $this->concreteConverter->canConvertMimetype('image/jpeg')) {
                $results[] = $this->concreteConverter->convert($jpgRef);
            }
        }

        // Automatically clean up the temporary files after the request is complete
        register_shutdown_function(static function () use ($filesToCleanUp) {
            foreach ($filesToCleanUp as $file) {
                @unlink($file);
            }
        });

        return new FileCollection(...$results);
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->canConvertSvg() || $this->canConvertWithImagick() || $this->concreteConverter->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        $types = [];
        $mime = new MimeTypes();
        if ($this->canConvertSvg()) {
            $types = array_merge($types, $mime->getMimeTypes('svg'));
        }

        if ($this->canConvertWithImagick()) {
            $types = array_merge($types, $this->imagickMimeTypes);
        }

        return array_unique(
            array_merge(
                $types,
                $this->concreteConverter->getAllowedMimeTypes()
            )
        );
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
}
