<?php

namespace App\Http\Controllers;

use App\Models\AppLocalizedText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocalizationController extends Controller
{
    /**
     * Get localized content by key
     *
     * @param string $contentKey
     * @return string|null
     */
    public function getLocalizedContent(string $contentKey)
    {
        $language = $this->getCurrentLanguage();
        $fallbackLanguage = 'de_DE';
        
        // Try loading the content in the current language
        $content = AppLocalizedText::getContent($contentKey, $language);
        
        // Fallback to the fallback language if not found
        if (!$content && $language !== $fallbackLanguage) {
            $content = AppLocalizedText::getContent($contentKey, $fallbackLanguage);
        }
        
        // Process placeholders in the content
        if ($content) {
            $content = $this->processPlaceholders($content);
        }
        
        return $content;
    }
    
    /**
     * Get all localized content for the current language
     *
     * @return array
     */
    public function getAllLocalizedContent()
    {
        $language = $this->getCurrentLanguage();
        $fallbackLanguage = 'en_US';
        
        // Get all content keys
        $contentKeys = AppLocalizedText::select('content_key')->distinct()->pluck('content_key')->toArray();
        
        $localizedContent = [];
        foreach ($contentKeys as $contentKey) {
            // Try loading the content in the current language
            $content = AppLocalizedText::getContent($contentKey, $language);
            
            // Fallback to the fallback language if not found
            if (!$content && $language !== $fallbackLanguage) {
                $content = AppLocalizedText::getContent($contentKey, $fallbackLanguage);
            }
            
            if ($content) {
                $localizedContent[$contentKey] = $content;
            }
        }
        
        // Process placeholders in the contents after all content is loaded
        $localizedContent = $this->processAllPlaceholders($localizedContent);
        
        return $localizedContent;
    }
    
    /**
     * Process placeholders in a single content string
     *
     * @param string $content
     * @return string
     */
    protected function processPlaceholders(string $content): string
    {
        $language = $this->getCurrentLanguage();
        $appName = AppLocalizedText::getContent('app_name', $language) ?? 'HAWKI';
        
        // Replace :system placeholder with app_name
        $content = str_replace(':system', $appName, $content);
        
        return $content;
    }
    
    /**
     * Process placeholders in all content items
     *
     * @param array $contents
     * @return array
     */
    protected function processAllPlaceholders(array $contents): array
    {
        // Get app_name from the array if available
        $appName = $contents['app_name'] ?? 'HAWKI';
        
        foreach ($contents as $key => $content) {
            if (is_string($content)) {
                $contents[$key] = str_replace(':system', $appName, $content);
            }
        }
        
        return $contents;
    }
    
    /**
     * Determine current language from session or app locale
     * Returns language in format like "de_DE" or "en_US"
     *
     * @return string
     */
    protected function getCurrentLanguage(): string
    {
        if (Session::has('language')) {
            $language = Session::get('language');
            if (isset($language['id']) && !empty($language['id'])) {
                return $language['id']; // Format already "de_DE" or "en_US"
            }
        }
        
        // Fallback: Convert app locale to proper format if needed
        $locale = App::getLocale();
        if (strlen($locale) == 2) {
            // Convert "de" to "de_DE", "en" to "en_US", etc.
            $localeMap = [
                'de' => 'de_DE',
                'en' => 'en_US',
                'fr' => 'fr_FR',
                'es' => 'es_ES',
                'it' => 'it_IT',
                // Add more mappings as needed
            ];
            return $localeMap[$locale] ?? $locale.'_'.strtoupper($locale);
        }
        
        return $locale;
    }
}
