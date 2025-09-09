<?php

namespace App\Services\TextImport;

use App\Models\AppSystemText;
use App\Models\AppLocalizedText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TextImportService
{
    /**
     * Import system texts from JSON files
     *
     * @param bool $forceUpdate Whether to update existing entries
     * @return int Number of texts imported
     */
    public function importSystemTexts(bool $forceUpdate = false): int
    {
        Log::info('Importing system texts...', ['force_update' => $forceUpdate]);
        
        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $count = 0;
        
        foreach ($supportedLanguages as $language) {
            $jsonFile = $languagePath . $language . '.json';
            
            if (File::exists($jsonFile)) {
                try {
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);
                    
                    if ($textData && is_array($textData)) {
                        foreach ($textData as $key => $value) {
                            if (!is_string($value) || empty($value)) {
                                continue;
                            }
                            
                            $result = $this->importSystemText($key, $language, $value, $forceUpdate);
                            if ($result->wasRecentlyCreated || $forceUpdate) {
                                $count++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error importing from {$jsonFile}: " . $e->getMessage());
                }
            }
        }
        
        Log::info("System text import completed: {$count} texts processed");
        return $count;
    }
    
    /**
     * Import localized texts from HTML files
     *
     * @param bool $forceUpdate Whether to update existing entries
     * @return int Number of texts imported
     */
    public function importLocalizedTexts(bool $forceUpdate = false): int
    {
        Log::info('Importing localized texts...', ['force_update' => $forceUpdate]);
        
        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $fileMapping = $this->buildDynamicFileMapping($languagePath, $supportedLanguages);
        $count = 0;
        
        foreach ($fileMapping as $filePrefix => $contentKey) {
            foreach ($supportedLanguages as $language) {
                $filePath = $languagePath . $filePrefix . '_' . $language . '.html';
                
                if (File::exists($filePath)) {
                    try {
                        $content = File::get($filePath);
                        
                        if (!empty($content)) {
                            $result = $this->importLocalizedText($contentKey, $language, $content, $forceUpdate);
                            if ($result->wasRecentlyCreated || $forceUpdate) {
                                $count++;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Error importing content from {$filePath}: {$e->getMessage()}");
                    }
                }
            }
        }
        
        Log::info("Localized text import completed: {$count} contents processed");
        return $count;
    }
    
    /**
     * Import a single system text
     */
    protected function importSystemText(string $key, string $language, string $content, bool $forceUpdate = false)
    {
        if ($forceUpdate) {
            return AppSystemText::updateOrCreate(
                ['content_key' => $key, 'language' => $language],
                ['content' => $content]
            );
        } else {
            return AppSystemText::firstOrCreate(
                ['content_key' => $key, 'language' => $language],
                ['content' => $content]
            );
        }
    }
    
    /**
     * Import a single localized text
     */
    protected function importLocalizedText(string $key, string $language, string $content, bool $forceUpdate = false)
    {
        if ($forceUpdate) {
            return AppLocalizedText::updateOrCreate(
                ['content_key' => $key, 'language' => $language],
                ['content' => $content]
            );
        } else {
            return AppLocalizedText::firstOrCreate(
                ['content_key' => $key, 'language' => $language],
                ['content' => $content]
            );
        }
    }
    
    /**
     * Build file mapping dynamically by scanning the language directory
     */
    protected function buildDynamicFileMapping(string $languagePath, array $supportedLanguages): array
    {
        $fileMapping = [];
        $files = File::files($languagePath);
        
        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            if (Str::endsWith($filename, '.html')) {
                foreach ($supportedLanguages as $language) {
                    $suffix = '_' . $language . '.html';
                    if (Str::endsWith($filename, $suffix)) {
                        $filePrefix = Str::beforeLast($filename, $suffix);
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
