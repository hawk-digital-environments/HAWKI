<?php
declare(strict_types=1);

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use App\Services\System\Http\UrlResolver;
use App\Services\System\Time\CarbonClockInterface;
use Exception;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Mime\MimeTypes;
use ZipArchive;


/**
 * Converts documents via the HAWKI file converter microservice
 * ({@link https://github.com/hawk-digital-environments/hawki-toolkit-file-converter}).
 *
 * Required config:
 *   - `api_url`  — base URL of the converter service, e.g. `http://file-converter` or
 *                  `https://example.test/hawki/converter` when it is nested behind a reverse
 *                  proxy. All endpoints (`/extract`, the service info page at `/`) are
 *                  addressed relative to it. A legacy value that still contains the
 *                  `/extract` endpoint is tolerated and stripped automatically.
 *   - `api_key`  — Bearer token for the converter service.
 */
class HawkiDocConverter extends AbstractFileConverter
{
    /**
     * Cached information supplied by service endpoint.
     */
    protected string $cacheKey = "hawki_converter_service_info";

    public function __construct(
        private readonly Repository           $cache,
        private readonly CarbonClockInterface $clock
    )
    {

    }

    /**
     * Returns the root URL of the converter service. All endpoints are addressed relative to
     * it, so the service keeps working when it is nested behind a reverse proxy on an
     * arbitrary sub path. A legacy configured value that still points at the `/extract`
     * endpoint itself is normalized by stripping the known suffix.
     */
    private function getServiceRootUrl(): string
    {
        return UrlResolver::stripPathSuffix($this->config['api_url'], '/extract');
    }

    /**
     * Builds the URL of an endpoint relative to the service root (empty path = the root itself).
     * A query string on the configured URL (e.g. a proxy token) is carried over, as it was
     * when the configured URL was posted to verbatim.
     */
    private function getEndpointUrl(string $path = ''): string
    {
        $url = $this->getServiceRootUrl() . ($path === '' ? '' : '/' . ltrim($path, '/'));
        $query = parse_url($this->config['api_url'], PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $url . '?' . $query : $url;
    }

    /**
     * Collects and returns a cached version of the service description endpoint of the
     * HAWKI file converter service root (`/`). The returned array contains a `supported_formats` key with an array of supported file extensions.
     * @return array
     */
    protected function getServiceInfo(): array
    {
        return $this->cache->remember(
            key: $this->cacheKey,
            ttl: $this->clock->now()->addDay(),
            callback: function () {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->config['api_key'],
                    'Accept' => 'application/json',
                ])->get($this->getEndpointUrl());
                return (array)array_merge(
                    ['supported_formats' => []],
                    $response->json() ?? []
                );
            }
        );
    }

    /**
     * Returns the MIME types accepted by the HAWKI converter.
     *
     * @return string[] Lowercase MIME type strings.
     */
    public function getAllowedMimeTypes(): array
    {
        $mime = new MimeTypes();

        $getMimeTypesFromFileExt = fn(string $fileExt): array => $mime->getMimeTypes(ltrim($fileExt, '.'));

        return array_values(array_unique(array_merge(
            ...array_map(
                $getMimeTypesFromFileExt,
                $this->getServiceInfo()["supported_formats"]
            )
        )));
    }

    /**
     * Always returns true — the HAWKI converter is considered available as long as its config is valid.
     * Actual connectivity is not checked; HTTP errors during {@see convert()} will throw instead.
     */
    public function isAvailable(): bool
    {
        return true;
    }

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
     * Writes the raw ZIP binary to a temp file, extracts it into `$extractToDirectory`,
     * then removes the temp file. Throws {@see ConversionFailedException} when the archive
     * cannot be opened.
     *
     * @param string $zipContent Raw binary content of the ZIP archive.
     * @param string $extractToDirectory Absolute path to an existing directory.
     */
    public function unzipContent(string $zipContent, string $extractToDirectory): bool
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'unzipped_') . '.zip';
        file_put_contents($tmpZip, $zipContent);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $zip->extractTo($extractToDirectory);
            $zip->close();
            unlink($tmpZip);
            return true;
        } else {
            unlink($tmpZip);
            throw ConversionFailedException::forString($this, "Failed to open ZIP file.");
        }
    }


    /**
     * POSTs the file to the HAWKI converter and unpacks the returned ZIP archive.
     *
     * The response body must be a valid ZIP file. Its contents are extracted to a temporary
     * directory and each entry is returned as a {@see FileReference}. A shutdown function is
     * registered to delete all temporary files once the request finishes.
     *
     * @throws ConversionFailedException if the API returns a non-2xx response, the temporary
     *         directory cannot be created, or the ZIP archive cannot be opened.
     * @throws Exception if PHP's ZipArchive extension encounters an unexpected error.
     */
    public function convert(FileReference $file): FileCollection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Accept' => 'application/json',
        ])
            ->timeout($this->getRequestTimeout())
            ->attach('file', $file->getStream(), $file->getOriginalFilename())
            ->post($this->getEndpointUrl('/extract'));

        if (!$response->successful()) {
            throw ConversionFailedException::forFailedResponse($this, $response);
        }

        // Unzip files from response
        $zipContent = $response->body();
        $extractDir = sys_get_temp_dir() . '/pdf_extract_' . uniqid('', true);
        if (!mkdir($extractDir, 0700, true) && !is_dir($extractDir)) {
            throw ConversionFailedException::forString($this, sprintf('Directory "%s" was not created', $extractDir));
        }

        $this->unzipContent($zipContent, $extractDir);

        // Optionally, read all extracted files and return as array [relative_path => file_content]
        $files = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir));
        foreach ($rii as $fileinfo) {
            if ($fileinfo->isFile()) {
                $files[] = FileReference::fromDisk(
                    diskFilePath: $fileinfo->getPathname(),
                    originalFilename: basename($fileinfo->getPathname()),
                );
            }
        }

        // Register a shutdown function to clean up the extracted files after the request is complete,
        // to avoid leaving temporary files on disk.
        register_shutdown_function(static function () use ($files) {
            foreach ($files as $file) {
                    @unlink($file->getDiskFilePath());
            }
        });

        return new FileCollection(...$files);
    }
}
