<?php

namespace App\Console\Commands;

use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Console\Command;
use Symfony\Component\Mime\MimeTypes;

class FileStorageConverterTypesList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filestorage:converter:types:list
                            {--extensions : Return file extensions instead of MIME types}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all MIME types (or file extensions) supported by the active file converter.';

    /**
     * Execute the console command.
     */
    public function handle(FileConverterInterface $converter): void
    {
        if (!$converter->isAvailable()) {
            $this->warn('No file converter is currently installed or available.');
            return;
        }

        $mimeTypes = array_values(array_unique($converter->getAllowedMimeTypes()));

        if ($this->option('extensions')) {
            $symfony = new MimeTypes();
            $extensions = [];
            foreach ($mimeTypes as $mime) {
                foreach ($symfony->getExtensions($mime) as $ext) {
                    $extensions[] = $ext;
                }
            }
            $extensions = array_values(array_unique($extensions));
            sort($extensions);

            $this->line(implode(PHP_EOL, $extensions));
        } else {
            sort($mimeTypes);
            $this->line(implode(PHP_EOL, $mimeTypes));
        }
    }
}
