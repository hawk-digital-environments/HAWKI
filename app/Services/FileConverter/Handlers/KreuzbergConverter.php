<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use Arr;
use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;

class KreuzbergConverter extends AbstractFileConverter
{
    public function __construct(
        private Repository     $cache,
        private ClockInterface $clock = new Clock()
    )
    {
    }

    /**
     * @inheritDoc
     */
    public static function isValidConfig(array $config): bool
    {
        return isset($config['api_url'])
            && is_string($config['api_url'])
            && filter_var($config['api_url'], FILTER_VALIDATE_URL);
    }

    /**
     * Clears the cache of the kreuzberg converter
     * @return bool
     */
    public function clearCache(): bool
    {
        return Http::delete($this->config['api_url'] . '/cache/clear')->successful();
    }

    /**
     * @inheritDoc
     */
    public function convert(FileReference $file): FileCollection
    {
        try {
            $response = Http::attach(
                'files',
                $file->getStream(),
                $file->getOriginalFilename()
            )->post(
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
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->cache->remember(
            key: 'kreuzberg_converter_mimetypes',
            ttl: (new Carbon($this->clock->now()))->addDay(),
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

                $filteredList = array_unique(array_map('strtolower', array_merge(...$mimeTypes)));

                // Remove svg from the list, because or

                return $filteredList;
            });
    }

    /**
     * @inheritDoc
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
