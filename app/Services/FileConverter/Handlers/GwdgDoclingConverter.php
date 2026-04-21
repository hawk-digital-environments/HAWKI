<?php

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class GwdgDoclingConverter extends AbstractFileConverter
{
    /**
     * @inheritDoc
     */
    public static function isValidConfig(array $config): bool
    {
        return isset($config['api_url'])
            && is_string($config['api_key'])
            && !empty($config['api_key'])
            && is_string($config['api_url'])
            && filter_var($config['api_url'], FILTER_VALIDATE_URL);
    }

    public function convert(FileReference $file): FileCollection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Accept' => 'application/json',
        ])
            ->timeout(240)
            ->attach('document', $file->getStream(), $file->getOriginalFilename())
            ->post($this->config['api_url']);

        if (!$response->successful()) {
            throw ConversionFailedException::forFailedResponse($this, $response);
        }

        $result = $response->json();

        $files = [];

        // Save markdown as .md file
        if (!empty($result['markdown'])) {
            $baseName = $result['filename'] ?? 'document';
            $files[] = FileReference::fromContent(
                originalFilename: $baseName . '.md',
                content: $result['markdown'],
            );
        }

        // Save images
        if (!empty($result['images']) && is_array($result['images'])) {
            foreach ($result['images'] as $img) {
                if (empty($img['image'])) {
                    continue;
                }

                // Parse "data:image/png;base64,..." format
                if (preg_match('/^data:(.*?);base64,(.*)$/', $img['image'], $matches)) {
                    $binary = base64_decode($matches[2]);
                } else {
                    $binary = base64_decode($img['image']);
                }

                $imgFilename = $img['filename'] ?? (Str::uuid() . '.png');
                $files[] = FileReference::fromContent(
                    originalFilename: $imgFilename,
                    content: $binary,
                );
            }
        }

        return new FileCollection(...$files);
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
        $mime = new MimeTypes();

        return array_values(array_unique([
            // Documents
            ...$mime->getMimeTypes('pdf'),
            ...$mime->getMimeTypes('docx'),
            ...$mime->getMimeTypes('pptx'),
            ...$mime->getMimeTypes('xlsx'),
            ...$mime->getMimeTypes('html'),
            ...$mime->getMimeTypes('htm'),
            ...$mime->getMimeTypes('md'),
            ...$mime->getMimeTypes('adoc'),
            ...$mime->getMimeTypes('csv'),
            ...$mime->getMimeTypes('vtt'),
            // Images
            ...$mime->getMimeTypes('png'),
            ...$mime->getMimeTypes('jpg'),
            ...$mime->getMimeTypes('jpeg'),
            ...$mime->getMimeTypes('tiff'),
            ...$mime->getMimeTypes('tif'),
            ...$mime->getMimeTypes('bmp'),
            ...$mime->getMimeTypes('webp'),
        ]));
    }
}
