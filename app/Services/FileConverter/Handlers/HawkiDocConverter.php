<?php
declare(strict_types=1);

namespace App\Services\FileConverter\Handlers;

use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use App\Services\System\Http\UrlResolver;
use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Psr\Clock\ClockInterface;
use App\Services\FileConverter\Exception\ConversionFailedException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Symfony\Component\Mime\MimeTypes;use Symfony\Component\Clock\Clock;
use Exception;


class HawkiDocConverter extends AbstractFileConverter {

    /**
     * Cached information supplied by service endpoint.
     */
    protected string $cacheKey = "hawki_converter_service_info";

    public function __construct(
        private readonly Repository $cache,
        private readonly ClockInterface $clock = new Clock()   
    ) {

    }

    /**
     * Collects and returns a cached version of the service description endpoint of the 
     * HAWKI file converter endpoint (`/`
     * @return array  
     */
    protected function getServiceInfo(): array
    {
        return $this->cache->remember(
            key: $this->cacheKey,
            ttl: (new Carbon($this->clock->now()))->addDay(),
            callback: function () {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->config['api_key'],
                    'Accept' => 'application/json',
                ])->get(UrlResolver::baseUrl($this->config['api_url']));
                $serviceInfo = $response->json();
                return $serviceInfo;
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
        
        $getMimeTypesFromFileExt = fn(string $fileExt): array => $mime->getMimeTypes(ltrim($fileExt,  '.'));

        $mimeTypes = array_values(array_unique(array_merge(
            ...array_map($getMimeTypesFromFileExt, $this->getServiceInfo()["supported_formats"]
        ))));
        
        return $mimeTypes;
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
     * @param string $zipContent  Raw binary content of the ZIP archive.
     * @param string $extractToDirectory  Absolute path to an existing directory.
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
            ->post($this->config['api_url']);

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