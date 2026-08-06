<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use App\Services\System\Time\CarbonClockInterface;
use Arr;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;

/**
 * Converter that delegates to a self-hosted Kreuzberg API instance for text and image extraction.
 *
 * Kreuzberg is an open-source document extraction service that accepts binary files via
 * multipart HTTP upload and returns structured JSON with extracted text and embedded images.
 * Files are POSTed to the `/extract` endpoint with a fixed base configuration (OCR via
 * Tesseract in German + English, image extraction at up to 300 DPI, page-marker injection)
 * that can be partially overridden per the `extraction_config` config key.
 *
 * Supported MIME types are fetched dynamically from the `/formats` endpoint and cached for 24 h.
 * SVG files are technically supported by Kreuzberg but {@see wouldLikeSomeoneElseToConvertMimetype()}
 * returns true for them so the wrapping {@see \App\Services\FileConverter\Utils\ImagePreProcessingConverter}
 * can intercept and produce a proper PNG rendering instead.
 *
 * Required config keys (under `file_converter.converters.kreuzberg`):
 *   - `api_url`           — base URL of the Kreuzberg service, e.g. `http://kreuzberg:8000`
 *   - `extraction_config` — (optional) key/value pairs merged on top of the default extraction payload
 */
class KreuzbergConverter extends AbstractFileConverter
{
    public function __construct(
        private readonly Repository           $cache,
        private readonly CarbonClockInterface $clock
    )
    {
    }

    /**
     * @inheritDoc
     * Requires `api_url` to be a valid URL.
     */
    public static function isValidConfig(array $config): bool
    {
        return isset($config['api_url'])
            && is_string($config['api_url'])
            && filter_var($config['api_url'], FILTER_VALIDATE_URL);
    }

    /**
     * Sends a DELETE request to the Kreuzberg `/cache/clear` endpoint to flush its server-side cache.
     * Returns true on success, false if the request fails.
     */
    public function clearCache(): bool
    {
        return Http::delete($this->config['api_url'] . '/cache/clear')->successful();
    }

    /**
     * POSTs the file to the Kreuzberg `/extract` endpoint and returns the extracted artefacts.
     *
     * Successful responses produce:
     * - A `{filename}_content.md` FileReference containing the extracted text (if non-empty).
     * - One binary FileReference per image under `images/{name}.{format}`.
     * - An optional `{name}_ocr.md` sidecar FileReference when the image has an OCR result.
     *
     * Mask images (`is_mask: true`) are silently skipped.
     *
     * @throws \App\Services\FileConverter\Exception\ConversionFailedException on any HTTP failure or unexpected exception.
     */
    public function convert(FileReference $file): FileCollection
    {
        try {
            $response = Http::attach(
                'files',
                $file->getStream(),
                $file->getOriginalFilename()
            )
                ->timeout($this->getRequestTimeout())
                ->post(
                    $this->config['api_url'] . '/extract',
                    [
                        'config' => json_encode(
                            Arr::mergeRecursive(
                                [
                                    'use_cache' => false,
                                    'ocr' => [
                                        'backend' => 'tesseract',
                                        'language' => 'deu+eng'
                                    ],
                                    'pdf_options' => [
                                        'extract_images' => true,
                                        'extract_metadata' => false,
                                        'hierarchy' => [
                                            'enabled' => false
                                        ]
                                    ],
                                    'images' => [
                                        'extract_images' => true,
                                        'target_dpi' => 100,
                                        'max_image_dimension' => 2048,
                                        'max_dpi' => 300,
                                        'auto_adjust_dpi' => true,
                                        'inject_placeholders' => true,
                                    ],
                                    'include_document_structure' => true,
                                    'pages' => [
                                        'extract_pages' => false,
                                        'insert_page_markers' => true,
                                        'marker_format' => "\n\n--- Page {page_num} ---\n\n"
                                    ]
                                ],
                                $this->config['extraction_config'] ?? []
                            ),
                            JSON_THROW_ON_ERROR
                        ),
                    ]
                );

            if (!$response->successful()) {
                throw ConversionFailedException::forFailedResponse($this, $response);
            }

            $res = $response->json();

            $files = [];

            $filenameWithoutExt = pathinfo($file->getOriginalFilename(), PATHINFO_FILENAME);

            $content = trim($res[0]['content'] ?? '');
            if (!empty($content)) {
                $files[] = FileReference::fromContent(
                    originalFilename: $filenameWithoutExt . '_content.md',
                    content: $content
                );
            }

            $images = $res[0]['images'] ?? [];
            if (is_array($images) && !empty($images)) {
                foreach ($images as $img) {
                    $dataBytes = $img['data'] ?? null;
                    if (empty($dataBytes)) {
                        continue;
                    }

                    // Ignore masks
                    if ($img['is_mask'] ?? false) {
                        continue;
                    }

                    // Build binary and store it
                    $dataBinary = pack('C*', ...$dataBytes);
                    $imageBasename = Path::join(
                        'images',
                        sprintf(
                            'image_%d_of_page_%d',
                            $img['image_index'] ?? 0,
                            $img['page_number'] ?? 0
                        ));
                    $files[] = FileReference::fromContent(
                        originalFilename: $imageBasename . '.' . ($img['format'] ?? 'png'),
                        content: $dataBinary
                    );

                    // Put potential ocr text into the markdown file as well
                    $ocrText = trim($img['ocr_result']['content'] ?? '');
                    if (!empty($ocrText)) {
                        $files[] = FileReference::fromContent(
                            originalFilename: $imageBasename . '_ocr.md',
                            content: $ocrText
                        );
                    }
                }
            }

            return new FileCollection(...$files);
        } catch (\Throwable $e) {
            throw ConversionFailedException::forThrowable($this, $e);
        }
    }

    /**
     * Always returns true — the Kreuzberg service is considered available as long as its config is valid.
     * Actual connectivity is not checked; HTTP errors during {@see convert()} will throw instead.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Fetches the list of supported MIME types from the Kreuzberg `/formats` endpoint.
     *
     * The result is cached for 24 hours to avoid a network round-trip on every request.
     * Both the `mime_type` field and any additional types derived from the file extension
     * (via Symfony MimeTypes) are included and de-duplicated.
     *
     * @return string[] Lowercase MIME type strings.
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->cache->remember(
            key: 'kreuzberg_converter_mimetypes',
            ttl: $this->clock->now()->addDay(),
            callback: function () {
                $response = Http::get(
                    $this->config['api_url'] . '/formats',
                );

                $mimeTypes = [];

                $mime = new MimeTypes();

                foreach ($response->json() as $format) {
                    $mimeTypes[] = [$format['mime_type']];
                    $mimeTypes[] = $mime->getMimeTypes($format['extension']);
                }

                return array_unique(array_map('strtolower', array_merge(...$mimeTypes)));
            });
    }

    /**
     * Returns true for `image/svg+xml`.
     *
     * Kreuzberg can convert SVG but only extracts embedded text — it does not render the graphic.
     * By yielding SVG to another handler, the wrapping {@see \App\Services\FileConverter\Utils\ImagePreProcessingConverter}
     * can convert the SVG to PNG first and then pass the PNG image to Kreuzberg for richer results.
     */
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool
    {
        // The KreuzbergConverter can technically convert svg files, but the result is, lets say "meh".
        // It only extracts the text content but does not provide an image output. So if we encounter svg files,
        // we want to skip them and let other converters handle them
        if ($mimetype === 'image/svg+xml') {
            return true;
        }

        return false;
    }
}
