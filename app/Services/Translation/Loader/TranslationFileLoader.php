<?php
declare(strict_types=1);


namespace App\Services\Translation\Loader;


use App\Services\Translation\Value\Locale;

readonly class TranslationFileLoader implements TranslationLoaderInterface
{
    public function __construct(
        private string $languagePath
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function load(Locale $locale): array
    {
        $prefix = $locale->lang;
        $translations = [];
        $defaultTranslations = [];
        
        // Filter and load files with the specific prefix
        foreach (scandir($this->languagePath, SCANDIR_SORT_NONE) as $file) {
            // Check if the file has the correct prefix
            if (!str_contains($file, $prefix)) {
                continue;
            }
            
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            
            if ($fileExtension === 'json') {
                // Read JSON file as associative array
                $fileContent = file_get_contents($this->languagePath . $file);
                $translationArray = json_decode($fileContent, true);
                
                if ($translationArray !== null) {
                    // Check if it's a default language file
                    if ($file === $prefix . '.json') {
                        $defaultTranslations[] = $translationArray;
                    } else {
                        $translations[] = $translationArray;
                    }
                }
                continue;
            }
            
            if ($fileExtension === 'html') {
                // Read HTML file and create a key-value pair
                $htmlContent = file_get_contents($this->languagePath . $file);
                $baseFileName = basename($file, '_' . $prefix . '.html');
                $keyName = '_' . $baseFileName;
                $translations[] = [$keyName => $htmlContent];
            }
        }
        
        return array_merge(...$defaultTranslations, ...$translations);
    }
}
