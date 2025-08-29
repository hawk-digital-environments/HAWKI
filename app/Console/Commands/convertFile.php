<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Toolkit\FileConverter\DocumentConverter;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

class convertFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:document {filePath : The full path to the file to convert}';
    protected $description = 'Convert a document to Markdown using DocumentConverter';


    public function handle()
    {
        $filePath = $this->argument('filePath');

        if (!file_exists($filePath)) {
            $this->error("File does not exist at path: {$filePath}");
            return 1;
        }

        try {
            $this->info("Converting document: {$filePath}");

            $converter = new DocumentConverter();
            $file = new SplFileInfo($filePath);
            $convertedFiles = $converter->requestDocumentToMarkdown($file);

            foreach ($convertedFiles as $relativePath => $content) {
                $this->info("Extracted file: {$relativePath}");
                Log::info("Extracted {$relativePath}", ['content' => $content]);
                // Optionally: Save to disk or further process
            }

            $this->info("Document conversion completed successfully.");
            return 0;

        } catch (\Throwable $e) {
            $this->error("Conversion failed: " . $e->getMessage());
            Log::error('Document conversion failed', ['exception' => $e]);
            return 1;
        }
    }
}
