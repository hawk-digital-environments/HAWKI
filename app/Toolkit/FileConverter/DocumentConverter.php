<?php

namespace App\Toolkit\FileConverter;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


class DocumentConverter
{

    function requestDocumentToMarkdown($file)
    {
        try{
            if ($file instanceof UploadedFile) {
                $resource = fopen($file->getRealPath(), 'r');
                $filename = $file->getClientOriginalName();
            } elseif ($file instanceof \SplFileInfo) {
                $resource = fopen($file->getPathname(), 'r');
                $filename = $file->getFilename();
            } elseif (is_string($file)) {
                // Assume string contains file contents (as returned from Storage::get())
                // Write to a temp file
                $tempFilePath = tempnam(sys_get_temp_dir(), 'upl_');
                file_put_contents($tempFilePath, $file);
                $resource = fopen($tempFilePath, 'r');
                $filename = 'file.pdf'; // Or dynamically assign if you know the original name
            } else {
                throw new \InvalidArgumentException("Invalid file input. Expected UploadedFile or SplFileInfo.");
            }

            $response = Http::attach(
                'file',
                $resource,
                $filename
            )->post(env('TOOLKIT_FILE_CONVERTER_URL', "http://127.0.0.1:8001/extract"));
            fclose($resource);

            if (!$response->successful()) {
                throw new Exception('PDF extraction failed: ' . $response->body());
            }

            // Unzip files from response
            $zipContent = $response->body();
            $extractDir = sys_get_temp_dir() . '/pdf_extract_' . uniqid();
            if (!mkdir($extractDir, 0700, true) && !is_dir($extractDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $extractDir));
            }

            $this->unzipContent($zipContent, $extractDir);

            // Optionally, read all extracted files and return as array [relative_path => file_content]
            $files = [];
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir));
            foreach ($rii as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $relativePath = substr($fileinfo->getPathname(), strlen($extractDir) + 1);
                    $files[$relativePath] = file_get_contents($fileinfo->getPathname());
                }
            }

            return $files;
        }
        catch(Exception $e){
            return null;
        }

    }


    private function unzipContent($zipContent, $extractToDirectory)
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
            throw new Exception("Failed to open ZIP file.");
        }
    }
}
