<?php
declare(strict_types=1);

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Converter that delegates to the GWDG Academic Cloud document conversion API (Docling).
 *
 * Documents are uploaded via multipart HTTP POST to the configured endpoint. The API returns
 * a JSON response containing a `markdown` field (extracted text) and an optional `images` array
 * (base64-encoded images found in the document, either as plain base64 or as `data:…;base64,…`
 * data URIs). Each image and the Markdown file are returned as separate {@see FileReference} entries.
 *
 * Required config keys (under `file_converter.converters.gwdg_docling`):
 *   - `api_url` — full URL of the Docling conversion endpoint
 *   - `api_key` — Bearer token for API authentication (non-empty string)
 */
class GwdgDoclingConverter extends AbstractFileConverter
{
    /**
     * @inheritDoc
     * Requires both a valid `api_url` and a non-empty `api_key`.
     */
    public static function isValidConfig(array $config): bool
    {
        return isset($config['api_url'], $config['api_key'])
            && is_string($config['api_url'])
            && filter_var($config['api_url'], FILTER_VALIDATE_URL)
            && is_string($config['api_key'])
            && !empty($config['api_key']);
    }

    /**
     * POSTs the file to the GWDG Docling API and returns the extracted artefacts.
     *
     * The response is expected to contain:
     * - `markdown` — extracted text; emitted as `{filename}.md`.
     * - `images`   — optional array of base64-encoded images; each emitted as its own FileReference.
     *
     * The request uses the converter's configured timeout (defaulting to 240 seconds via
     * {@see AbstractFileConverter::getRequestTimeout()}) because large documents can take
     * significant time to process.
     *
     * @throws ConversionFailedException if the API returns a non-2xx response.
     */
    public function convert(FileReference $file): FileCollection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Accept' => 'application/json',
        ])
            ->timeout($this->getRequestTimeout())
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
     * Always returns true — the GWDG API is considered available as long as its config is valid.
     * Actual connectivity is not checked; HTTP errors during {@see convert()} will throw instead.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Returns the hard-coded list of MIME types supported by the GWDG Docling API.
     * Covers common office documents (PDF, DOCX, PPTX, XLSX), markup (HTML, Markdown, AsciiDoc),
     * data files (CSV, VTT), and raster images (PNG, JPEG, TIFF, BMP, WebP).
     *
     * @return string[] Lowercase MIME type strings.
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
