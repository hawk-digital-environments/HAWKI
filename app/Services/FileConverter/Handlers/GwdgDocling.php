<?php

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Handlers\Interfaces\FileConverterInterface;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class GwdgDocling implements FileConverterInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function convert(UploadedFile|SplFileInfo|string $file): array
    {
        if ($file instanceof UploadedFile) {
            $resource = fopen($file->getRealPath(), 'r');
            $filename = $file->getClientOriginalName();
        } elseif ($file instanceof \SplFileInfo) {
            $resource = fopen($file->getPathname(), 'r');
            $filename = $file->getFilename();
        } elseif (is_string($file)) {
            // Assume string contains file contents (like Storage::get())
            $tempFilePath = tempnam(sys_get_temp_dir(), 'upl_');
            file_put_contents($tempFilePath, $file);
            $resource = fopen($tempFilePath, 'r');
            $filename = 'file.pdf';
        } else {
            throw new \InvalidArgumentException("Invalid file input. Expected UploadedFile, SplFileInfo, or string.");
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Accept'        => 'application/json',
        ])
        ->timeout(240)
        ->attach('document', $resource, $filename)
        ->post($this->config['api_url']);

        fclose($resource);

        if (!$response->successful()) {
            throw new Exception('PDF extraction failed: ' . $response->body());
        }

        $result = $response->json();

        $files = [];

        // Save markdown as .md file
        if (!empty($result['markdown'])) {
            $baseName = $result['filename'] ?? 'document';
            $files[$baseName . '.md'] = $result['markdown'];
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
                $files[$imgFilename] = $binary;
            }
        }

        return $files;
    }
}
