<?php

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\Storage\Value\FileCollection;
use App\Services\Storage\Value\FileReference;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Mime\MimeTypes;
use ZipArchive;

class HawkiDocConverter extends AbstractFileConverter
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

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        $mime = new MimeTypes();

        return [
            ...$mime->getMimeTypes('pdf'),
            ...$mime->getMimeTypes('doc'),
            ...$mime->getMimeTypes('docx'),
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function convert(FileReference $file): FileCollection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Accept' => 'application/json',
        ])
            ->attach('file', $file->getStream(), $file->getOriginalFilename())
            ->post($this->config['api_url']);

        if (!$response->successful()) {
            \Log::error('PDF extraction failed: ' . $response->body());
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

    private function unzipContent($zipContent, $extractToDirectory): bool
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
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return true;
    }
}
