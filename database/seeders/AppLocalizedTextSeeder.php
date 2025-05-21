<?php

namespace Database\Seeders;

use App\Models\AppLocalizedText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AppLocalizedTextSeeder extends Seeder
{
    /**
     * Seed the application's localized texts.
     *
     * @return void
     */
    public function run()
    {
        Log::info('Running AppLocalizedText seeder...');
        
        // Base path to the language folder
        $languagePath = resource_path('language/');
        
        // Supported languages
        $supportedLanguages = ['de_DE', 'en_US'];
        
        // Dynamically scan files and create fileMapping
        $fileMapping = $this->buildDynamicFileMapping($languagePath, $supportedLanguages);
        
        Log::info('Dynamically generated fileMapping:', $fileMapping);
        
        $count = 0;
        
        // For each file and language
        foreach ($fileMapping as $filePrefix => $contentKey) {
            foreach ($supportedLanguages as $language) {
                $filePath = $languagePath . $filePrefix . '_' . $language . '.html';
                
                if (File::exists($filePath)) {
                    try {
                        $content = File::get($filePath);
                        
                        if (!empty($content)) {
                            // Saving the content in the database
                            AppLocalizedText::setContent($contentKey, $language, $content);
                            $count++;
                            Log::info("Imported {$filePrefix} content for {$language}");
                        } else {
                            Log::warning("File {$filePath} is empty.");
                        }
                    } catch (\Exception $e) {
                        Log::error("Error importing content from {$filePath}: {$e->getMessage()}");
                    }
                } else {
                    Log::warning("File {$filePath} not found.");
                }
            }
        }
        
        Log::info("AppLocalizedText seeder completed: {$count} contents created or updated");
        $this->command->info("AppLocalizedText seeder completed: {$count} contents created or updated");
    }

    /**
     * Build file mapping dynamically by scanning the language directory
     *
     * @param string $languagePath
     * @param array $supportedLanguages
     * @return array
     */
    protected function buildDynamicFileMapping(string $languagePath, array $supportedLanguages): array
    {
        $fileMapping = [];
        $files = File::files($languagePath);
        
        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            // Only include HTML files
            if (Str::endsWith($filename, '.html')) {
                // Check if the filename matches one of the formats (e.g. Name_de_DE.html)
                foreach ($supportedLanguages as $language) {
                    $suffix = '_' . $language . '.html';
                    if (Str::endsWith($filename, $suffix)) {
                        $filePrefix = Str::beforeLast($filename, $suffix);
                        
                        // Create a content_key from the prefix (snake_case)
                        $contentKey = Str::snake(Str::camel($filePrefix));
                        
                        $fileMapping[$filePrefix] = $contentKey;
                        break;
                    }
                }
            }
        }
        
        return $fileMapping;
    }
}

