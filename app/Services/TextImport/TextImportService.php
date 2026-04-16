<?php

namespace App\Services\TextImport;

use App\Models\AppLocalizedText;
use App\Models\AppSystemText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TextImportService
{
    /**
     * Import system texts from JSON files
     *
     * @param  bool  $forceUpdate  Whether to update existing entries
     * @return int Number of texts imported
     */
    public function importSystemTexts(bool $forceUpdate = false): int
    {
        Log::info('Importing system texts...', ['force_update' => $forceUpdate]);

        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $count = 0;

        foreach ($supportedLanguages as $language) {
            $jsonFile = $languagePath.$language.'.json';

            if (File::exists($jsonFile)) {
                try {
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);

                    if ($textData && is_array($textData)) {
                        foreach ($textData as $key => $value) {
                            if (! is_string($value) || empty($value)) {
                                continue;
                            }

                            $result = $this->importSystemText($key, $language, $value, $forceUpdate);
                            if ($result->wasRecentlyCreated || $forceUpdate) {
                                $count++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error importing from {$jsonFile}: ".$e->getMessage());
                }
            }
        }

        Log::info("System text import completed: {$count} texts processed");

        return $count;
    }

    /**
     * Analyze system texts from JSON files without importing
     * Returns statistics about what would be imported
     *
     * @param  bool  $forceUpdate  Whether to consider force update mode
     * @return array Analysis results with statistics
     */
    public function analyzeSystemTexts(bool $forceUpdate = false): array
    {
        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        
        $analysis = [
            'languages' => [],
            'total_keys' => 0,
            'new_keys' => 0,
            'existing_keys' => 0,
        ];

        foreach ($supportedLanguages as $language) {
            $jsonFile = $languagePath.$language.'.json';
            
            $analysis['languages'][$language] = [
                'new' => 0,
                'existing' => 0,
                'total' => 0,
            ];

            if (File::exists($jsonFile)) {
                try {
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);

                    if ($textData && is_array($textData)) {
                        foreach ($textData as $key => $value) {
                            if (! is_string($value) || empty($value)) {
                                continue;
                            }

                            $analysis['languages'][$language]['total']++;
                            $analysis['total_keys']++;

                            // Check if key exists in database
                            $exists = AppSystemText::where('content_key', $key)
                                ->where('language', $language)
                                ->exists();

                            if ($exists) {
                                $analysis['languages'][$language]['existing']++;
                                $analysis['existing_keys']++;
                            } else {
                                $analysis['languages'][$language]['new']++;
                                $analysis['new_keys']++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error analyzing {$jsonFile}: ".$e->getMessage());
                }
            }
        }

        return $analysis;
    }

    /**
     * Import localized texts from HTML files
     *
     * @param  bool  $forceUpdate  Whether to update existing entries
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
                $filePath = $languagePath.$filePrefix.'_'.$language.'.html';

                if (File::exists($filePath)) {
                    try {
                        $content = File::get($filePath);

                        if (! empty($content)) {
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
        $description = $this->getLocalizedTextDescription($key);

        if ($forceUpdate) {
            return AppLocalizedText::updateOrCreate(
                ['content_key' => $key, 'language' => $language],
                [
                    'content' => $content,
                    'description' => $description,
                ]
            );
        } else {
            return AppLocalizedText::firstOrCreate(
                ['content_key' => $key, 'language' => $language],
                [
                    'content' => $content,
                    'description' => $description,
                ]
            );
        }
    }

    /**
     * Get the description for a localized text content key
     */
    protected function getLocalizedTextDescription(string $contentKey): string
    {
        $descriptions = [
            'data_protection' => 'Datenschutzerklärung - Angezeigt in Footer-Links und bei Nutzerregistrierung',
            'group_welcome' => 'Willkommensnachricht - Angezeigt im Bereich Gruppenchats',
        ];

        return $descriptions[$contentKey] ?? "Lokalisierter Text für: {$contentKey}";
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
                    $suffix = '_'.$language.'.html';
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

    /**
     * Import a specific system text key from JSON files
     *
     * @param  string  $contentKey  The specific content key to import
     * @return int Number of texts imported
     */
    public function importSpecificSystemText(string $contentKey): int
    {
        Log::info("Importing specific system text: {$contentKey}");

        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $count = 0;

        foreach ($supportedLanguages as $language) {
            $jsonFile = $languagePath.$language.'.json';

            if (File::exists($jsonFile)) {
                try {
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);

                    if ($textData && is_array($textData) && isset($textData[$contentKey])) {
                        $value = $textData[$contentKey];

                        if (is_string($value) && ! empty($value)) {
                            $result = $this->importSystemText($contentKey, $language, $value, true);
                            if ($result->wasRecentlyCreated || $result->wasChanged()) {
                                $count++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error importing {$contentKey} from {$jsonFile}: ".$e->getMessage());
                }
            }
        }

        Log::info("Imported specific system text: {$contentKey}", ['count' => $count]);

        return $count;
    }

    /**
     * Import a specific localized text key from HTML files
     *
     * @param  string  $contentKey  The specific content key to import
     * @return int Number of texts imported
     */
    public function importSpecificLocalizedText(string $contentKey): int
    {
        Log::info("Importing specific localized text: {$contentKey}");

        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $count = 0;

        // Build file mapping to find the correct file prefix for this content key
        $fileMapping = $this->buildDynamicFileMapping($languagePath, $supportedLanguages);

        // Find the file prefix that matches our content key
        $filePrefix = null;
        foreach ($fileMapping as $prefix => $mappedKey) {
            if ($mappedKey === $contentKey) {
                $filePrefix = $prefix;
                break;
            }
        }

        if ($filePrefix) {
            // Import from HTML files
            foreach ($supportedLanguages as $language) {
                $filePath = $languagePath.$filePrefix.'_'.$language.'.html';

                if (File::exists($filePath)) {
                    try {
                        $content = File::get($filePath);

                        if (! empty($content)) {
                            $result = $this->importLocalizedText($contentKey, $language, $content, true);
                            if ($result->wasRecentlyCreated || $result->wasChanged()) {
                                $count++;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Error importing {$contentKey} from {$filePath}: ".$e->getMessage());
                    }
                }
            }
        } else {
            // Fallback: try to import from JSON files for backward compatibility
            foreach ($supportedLanguages as $language) {
                $jsonFile = $languagePath.$language.'.json';

                if (File::exists($jsonFile)) {
                    try {
                        $jsonContent = File::get($jsonFile);
                        $textData = json_decode($jsonContent, true);

                        if ($textData && is_array($textData) && isset($textData[$contentKey])) {
                            $value = $textData[$contentKey];

                            if (is_string($value) && ! empty($value)) {
                                $result = $this->importLocalizedText($contentKey, $language, $value, true);
                                if ($result->wasRecentlyCreated || $result->wasChanged()) {
                                    $count++;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Error importing {$contentKey} from {$jsonFile}: ".$e->getMessage());
                    }
                }
            }
        }

        Log::info("Imported specific localized text: {$contentKey}", ['count' => $count]);

        return $count;
    }
}
