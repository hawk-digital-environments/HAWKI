<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileConverter\Interfaces\FileConverterExtensionInterface;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Console\Command;
use Symfony\Component\Mime\MimeTypes;

class FileStorageConverterTypesList extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = <<<'EOD'
filestorage:converter:types:list
                            {--extensions : Return file extensions instead of MIME types}
EOD;

    /**
     * The console command description.
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

        $convChain = $this->getConverterChain($converter);
        $seenMimes = [];

        foreach ($convChain as $convLayer) {
            $addedMimeTypes = array_values(array_diff($convLayer->getAllowedMimeTypes(), $seenMimes));
            $seenMimes = array_unique(array_merge($seenMimes, $addedMimeTypes));
            $this->showConverterInfo($convLayer, $addedMimeTypes);
        }
    }

    /**
     * Return the file converters. The nested converters are returned at the beginning.
     */
    private function getConverterChain(FileConverterInterface $converter): array
    {
        if (!$converter instanceof FileConverterExtensionInterface) {
            return [$converter];
        }

        return array_merge(
            $this->getConverterChain($converter->getInnerConverter()),
            [$converter],
        );
    }

    /**
     * Prints one labelled block for a converter layer.
     *
     * @param FileConverterInterface $converter the file converter class to print info for
     * @param list<string>           $mimeTypes MIME types contributed by this layer
     */
    private function showConverterInfo(FileConverterInterface $converter, array $mimeTypes): void
    {
        $this->line('');
        $this->line($converter::class . ':');

        if (empty($mimeTypes)) {
            $this->line('  (No added types)');

            return;
        }

        $values = $mimeTypes;

        if ($this->option('extensions')) {
            $symfony = new MimeTypes();
            $extensions = [];

            foreach ($mimeTypes as $mime) {
                foreach ($symfony->getExtensions($mime) as $ext) {
                    $extensions[] = $ext;
                }
            }

            $values = array_values(array_unique($extensions));
        }

        sort($values);

        foreach ($values as $value) {
            $this->line('  ' . $value);
        }
    }
}
