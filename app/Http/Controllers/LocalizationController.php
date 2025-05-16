<?php

namespace App\Http\Controllers;

use App\Models\AppLocalizedText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class LocalizationController extends Controller
{
    // Static cache for the current request to avoid repeated database queries
    protected static $requestCache = [];
    
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
        
        // Check if the element is already in the static request cache
        $requestCacheKey = "localized_{$contentKey}_{$language}";
        if (isset(self::$requestCache[$requestCacheKey])) {
            return self::$requestCache[$requestCacheKey];
        }
        
        // Cache key for this specific content
        $cacheKey = "localized_content_{$contentKey}_{$language}";
        
        // Use Cache::remember for this request
        $content = Cache::remember($cacheKey, now()->addHours(1), function () use ($contentKey, $language, $fallbackLanguage) {
            // Try loading the content in the current language
            $content = AppLocalizedText::getContent($contentKey, $language);
            
            // Fallback to the fallback language if not found
            if (!$content && $language !== $fallbackLanguage) {
                $content = AppLocalizedText::getContent($contentKey, $fallbackLanguage);
            }
            
            // Process placeholders in the content
            if ($content) {
                $content = $this->processPlaceholders($content, $language);
            }
            
            return $content;
        });
        
        // Save in the static request cache
        self::$requestCache[$requestCacheKey] = $content;
        
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
        
        // Check if the contents are already in the static request cache
        $requestCacheKey = "all_localized_{$language}";
        if (isset(self::$requestCache[$requestCacheKey])) {
            return self::$requestCache[$requestCacheKey];
        }
        
        // Cache key for all localized content for this language
        $cacheKey = "all_localized_content_{$language}";
        
        // Use Cache::remember for this request
        $localizedContent = Cache::remember($cacheKey, now()->addHours(1), function () use ($language, $fallbackLanguage) {
            // Get all placeholders for this language
            $placeholders = $this->replacePlaceholders([], $language);
            
            // Retrieve all content keys
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
                    // Replace placeholders directly
                    $localizedContent[$contentKey] = strtr($content, $placeholders);
                }
            }
            
            return $localizedContent;
        });
        
        // Save in the static request cache
        self::$requestCache[$requestCacheKey] = $localizedContent;
        
        return $localizedContent;
    }
    
    /**
     * Replace placeholders with their values
     * Returns either a replacement mapping array or processes text directly
     * 
     * @param string|array $content Content to process or empty array for mapping only
     * @param string $language Current language
     * @return array|string Replacement mapping or processed content
     */
    protected function replacePlaceholders($content = [], string $language = null): array|string
    {
        if (!$language) {
            $language = $this->getCurrentLanguage();
        }
        
        // Check if the placeholders are already in the static request cache
        $requestCacheKey = "placeholders";
        if (isset(self::$requestCache[$requestCacheKey])) {
            $placeholders = self::$requestCache[$requestCacheKey];
        } else {
            // Cache for placeholders
            $cacheKey = "placeholders";
            
            $placeholders = Cache::remember($cacheKey, now()->addDay(), function () {
                // Build placeholder mapping
                $mapping = [];
                
                // Get app name from config
                $appName = config('app.name');
                $mapping[':system'] = $appName;
                
                // Additional placeholders can be added here
                // $mapping[':placeholder'] = $value;
                
                return $mapping;
            });
            
            // Save in the static request cache
            self::$requestCache[$requestCacheKey] = $placeholders;
        }
        
        // If a string is provided, replace the placeholders in it
        if (is_string($content)) {
            return strtr($content, $placeholders);
        }
        
        // Otherwise, return the mapping
        return $placeholders;
    }
    
    /**
     * Process placeholders in a single content string
     *
     * @param string $content
     * @param string $language
     * @return string
     */
    protected function processPlaceholders(string $content, string $language): string
    {
        // Use the new replacePlaceholders method that returns a result
        return $this->replacePlaceholders($content, $language);
    }
    
    /**
     * Process placeholders in all content items
     *
     * @param array $contents
     * @return array
     */
    protected function processAllPlaceholders(array $contents): array
    {
        // Get language from current context
        $language = $this->getCurrentLanguage();
        
        // Get placeholder mapping
        $placeholders = $this->replacePlaceholders([], $language);
        
        foreach ($contents as $key => $content) {
            if (is_string($content)) {
                $contents[$key] = strtr($content, $placeholders);
            }
        }
        
        return $contents;
    }
    
    /**
     * Determine current language from session or app locale
     *
     * @return string
     */
    protected function getCurrentLanguage(): string
    {
        // Check if the language is already in the static request cache
        if (isset(self::$requestCache['current_language'])) {
            return self::$requestCache['current_language'];
        }
        
        $language = null;
        
        if (Session::has('language')) {
            $language = Session::get('language');
            if (isset($language['id']) && !empty($language['id'])) {
                $language = $language['id']; // Format already "de_DE" or "en_US"
            }
        }
        
        if (!$language) {
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
                $language = $localeMap[$locale] ?? $locale.'_'.strtoupper($locale);
            } else {
                $language = $locale;
            }
        }
        
        // Save in the static request cache
        self::$requestCache['current_language'] = $language;
        
        return $language;
    }
    
    /**
     * Clear all related caches (for use in TextSettingsScreen)
     */
    public static function clearCaches(string $language = null)
    {
        if ($language) {
            // Clear specific language caches
            Cache::forget("app_name_{$language}");
            Cache::forget("all_localized_content_{$language}");
            
            // Clear content-specific caches for this language
            $contentKeys = AppLocalizedText::select('content_key')
                ->where('language', $language)
                ->distinct()
                ->pluck('content_key')
                ->toArray();
                
            foreach ($contentKeys as $key) {
                Cache::forget("localized_content_{$key}_{$language}");
            }
        } else {
            // Clear request cache
            self::$requestCache = [];
            
            // Get all supported languages
            $supportedLanguages = ['de_DE', 'en_US']; // Hardcoded for simplicity
            
            // Clear caches for all languages
            foreach ($supportedLanguages as $lang) {
                self::clearCaches($lang);
            }
        }
    }
}