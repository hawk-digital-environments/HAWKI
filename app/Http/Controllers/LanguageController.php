<?php

namespace App\Http\Controllers;

use App\Models\AppSystemText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    // Static cache for the current request to avoid repeated accesses
    protected static $requestCache = [];

    // / Changes the language based on previous values or falls back to default parameters
    public function getTranslation()
    {
        $langs = config('locale.langs');
        // LANGUAGE CHANGE...
        if (Session::has('language')) {
            $language = Session::get('language');
        } else {
            // Try to get cookie from last use
            if (Cookie::get('lastLanguage_cookie') && Cookie::get('lastLanguage_cookie') != '') {
                $language = $langs[Cookie::get('lastLanguage_cookie')];
            } else {
                // If there's no cookie, try the default language from config or use a hardcoded default
                $language = $langs[config('locale.default_language')];
            }
        }
        if (gettype($language) == 'string') {
            $language = $langs[config($language)];
        }

        // Store the language in session
        Session::put('language', $language);
        
        // Check language controller system configuration:
        // false (0) = Load from JSON files (resources/language/*.json)
        // true (1)  = Load from database (app_system_texts table)
        $useDatabase = (bool) config('hawki.language_controller_system', false);
        
        // AI prompts are always controlled by ai_config_system, independent of language_controller_system
        $useAiDatabaseConfig = (bool) config('hawki.ai_config_system', false);
        
        if ($useDatabase) {
            // Load from database (new behavior) - AppSystemText model
            $translation = $this->fetchTranslationFiles($language['id'], $useAiDatabaseConfig);
        } else {
            // Load from JSON files (legacy behavior) - JSON files in resources/language/
            $translation = $this->fetchTranslationFromFiles($language['id'], $useAiDatabaseConfig);
        }

        // Process placeholders in all translations
        $translation = $this->processAllPlaceholders($translation, $language['id']);

        return $translation;
    }

    // / Changes language based on the request language
    public function changeLanguage(Request $request)
    {
        $validatedData = $request->validate([
            'inputLang' => 'required|string|size:5',
        ]);
        $langId = $validatedData['inputLang'];

        $langs = config('locale.langs');
        $language = $langs[$langId];

        if (! $language) {
            error_log('bad lang');

            return response()->json(['success' => false, 'error' => 'Invalid language'], 400);
        }

        // Store the new language in session
        Session::put('language', $language);

        // Load the language files
        $translation = $this->fetchTranslationFiles($language['id']);

        // Set cookie
        $response = response()->json([
            'success' => true,
        ]);

        // Set the language cookie for 120 days (equivalent to 4 months)
        Cookie::queue('lastLanguage_cookie', $language['id'], 60 * 24 * 120); // Store cookie for 120 days

        return $response;
    }

    // / Returns an array of languages
    public function getAvailableLanguages()
    {
        $languages = config('locale')['langs'];
        $availableLocale = [];
        foreach ($languages as $lang) {
            if ($lang['active']) {
                array_push($availableLocale, $lang);
            }
        }

        return $availableLocale;
    }

    private function fetchTranslationFiles($prefix, $useAiDatabaseConfig = false)
    {
        // Use Laravel Cache to cache the translations with AI config dependency
        $cacheKey = "translations_{$prefix}_ai_" . ($useAiDatabaseConfig ? 'db' : 'config');
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($prefix, $useAiDatabaseConfig) {
            $translations = AppSystemText::where('language', $prefix)
                ->get()
                ->pluck('content', 'content_key')
                ->toArray();

            // Load prompts based on AI config system setting
            if ($useAiDatabaseConfig) {
                // AI Config System = Database: Load prompts from ai_assistants_prompts table
                $prompts = \App\Models\AiAssistantPrompt::where('language', $prefix)
                    ->get()
                    ->mapWithKeys(function ($prompt) {
                        // Convert "Default Prompt" to "Default_Prompt" for JS compatibility
                        $jsKey = str_replace(' ', '_', $prompt->title);
                        return [$jsKey => $prompt->content];
                    })
                    ->toArray();
                
                $translations = array_merge($translations, $prompts);
            } else {
                // AI Config System = Config: Load prompts from JSON files
                $languageDir = resource_path("language");
                
                if (is_dir($languageDir)) {
                    // Load prompt JSON files (e.g., prompts_de_DE.json)
                    $promptFiles = glob($languageDir . "/prompts_{$prefix}.json");
                    
                    foreach ($promptFiles as $file) {
                        $content = file_get_contents($file);
                        $data = json_decode($content, true);
                        
                        if (is_array($data)) {
                            $translations = array_merge($translations, $data);
                        }
                    }
                    
                    // If no prompt JSON files exist, use database as fallback
                    if (empty($promptFiles)) {
                        $prompts = \App\Models\AiAssistantPrompt::where('language', $prefix)
                            ->get()
                            ->mapWithKeys(function ($prompt) {
                                // Convert "Default Prompt" to "Default_Prompt" for JS compatibility
                                $jsKey = str_replace(' ', '_', $prompt->title);
                                return [$jsKey => $prompt->content];
                            })
                            ->toArray();
                        
                        $translations = array_merge($translations, $prompts);
                    }
                }
            }

            return $translations;
        });
    }

    private function fetchTranslationFromFiles($prefix, $useAiDatabaseConfig = false)
    {
        // Use Laravel Cache to cache the translations from JSON files with AI config dependency
        $cacheKey = "json_translations_{$prefix}_ai_" . ($useAiDatabaseConfig ? 'db' : 'config');
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($prefix, $useAiDatabaseConfig) {
            $translations = [];
            
            // Load all JSON files from resources/language directory
            $languageDir = resource_path("language");
            
            if (is_dir($languageDir)) {
                // Pattern 1: Files that end with _{prefix}.json (e.g., custom_de_DE.json, prompts_de_DE.json)
                $files = glob($languageDir . "/*_{$prefix}.json");
                
                // Pattern 2: Files that are exactly {prefix}.json (e.g., de_DE.json)
                $mainFile = $languageDir . "/{$prefix}.json";
                if (file_exists($mainFile)) {
                    $files[] = $mainFile;
                }
                
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (is_array($data)) {
                        $translations = array_merge($translations, $data);
                    }
                }
            }
            
            // Handle prompts based on AI config system setting
            if ($useAiDatabaseConfig) {
                // AI Config System = Database: Load prompts from ai_assistants_prompts table
                $prompts = \App\Models\AiAssistantPrompt::where('language', $prefix)
                    ->get()
                    ->mapWithKeys(function ($prompt) {
                        // Convert "Default Prompt" to "Default_Prompt" for JS compatibility
                        $jsKey = str_replace(' ', '_', $prompt->title);
                        return [$jsKey => $prompt->content];
                    })
                    ->toArray();
                
                $translations = array_merge($translations, $prompts);
            } else {
                // AI Config System = Config: Only load JSON prompts, not database prompts
                // Check if prompt JSON files exist, if not use database as fallback
                $promptJsonExists = false;
                if (is_dir($languageDir)) {
                    $promptFiles = glob($languageDir . "/prompts_{$prefix}.json");
                    $promptJsonExists = !empty($promptFiles);
                }
                
                if (!$promptJsonExists) {
                    // Load database prompts as fallback only if no prompt JSON files exist
                    $prompts = \App\Models\AiAssistantPrompt::where('language', $prefix)
                        ->get()
                        ->mapWithKeys(function ($prompt) {
                            // Convert "Default Prompt" to "Default_Prompt" for JS compatibility
                            $jsKey = str_replace(' ', '_', $prompt->title);
                            return [$jsKey => $prompt->content];
                        })
                        ->toArray();
                    
                    $translations = array_merge($translations, $prompts);
                }
            }
            
            return $translations;
        });
    }

    /**
     * Process placeholders in all translation items
     *
     * @param  array  $translations  Array of translations
     * @param  string  $language  Current language code (e.g., 'de_DE')
     */
    protected function processAllPlaceholders(array $translations, string $language): array
    {
        // Get placeholders map for this language
        $placeholders = $this->getPlaceholders($language);

        // Replace placeholders in all translations
        foreach ($translations as $key => $value) {
            if (is_string($value)) {
                $translations[$key] = strtr($value, $placeholders);
            }
        }

        return $translations;
    }

    /**
     * Get placeholder replacements for a specific language
     *
     * @return array Associative array of placeholder => replacement value
     */
    protected function getPlaceholders(string $language): array
    {
        // Check if placeholders are already in request cache
        $requestCacheKey = "placeholders_{$language}";
        if (isset(self::$requestCache[$requestCacheKey])) {
            return self::$requestCache[$requestCacheKey];
        }

        // Use persistent cache for placeholders with a longer lifetime
        $cacheKey = "system_placeholders_{$language}";

        $placeholders = Cache::remember($cacheKey, now()->addDay(), function () use ($language) {
            // Build placeholder mapping
            $mapping = [];

            // Get app name
            $appName = $this->getCachedAppName($language);
            $mapping[':system'] = $appName;

            // Additional placeholders can be added here
            // $mapping[':placeholder'] = $value;

            return $mapping;
        });

        // Store in request cache
        self::$requestCache[$requestCacheKey] = $placeholders;

        return $placeholders;
    }

    /**
     * Get and cache the application name
     */
    protected function getCachedAppName(string $language): string
    {
        // Check request cache first
        $requestCacheKey = 'app_name';
        if (isset(self::$requestCache[$requestCacheKey])) {
            return self::$requestCache[$requestCacheKey];
        }

        // Use persistent cache
        $cacheKey = 'app_name';

        $appName = Cache::remember($cacheKey, now()->addDay(), function () {
            // Get app name from config instead of database
            return config('app.name');
        });

        // Store in request cache
        self::$requestCache[$requestCacheKey] = $appName;

        return $appName;
    }

    /**
     * Clear all language-related caches (for use in system text operations)
     * This method clears translation caches including AI Assistant prompts.
     * Should be called when system texts or prompts are updated via Orchid Admin Panel.
     */
    public static function clearCaches(?string $language = null)
    {
        if ($language) {
            // Clear specific language caches - include AI config variants
            Cache::forget("translations_{$language}");
            Cache::forget("translations_{$language}_ai_db");
            Cache::forget("translations_{$language}_ai_config");
            Cache::forget("json_translations_{$language}");
            Cache::forget("json_translations_{$language}_ai_db");
            Cache::forget("json_translations_{$language}_ai_config");
            Cache::forget("system_placeholders_{$language}");
            Cache::forget("app_name_{$language}");
        } else {
            // Clear request cache
            self::$requestCache = [];

            // Get all supported languages
            $supportedLanguages = ['de_DE', 'en_US'];

            // Clear caches for all languages
            foreach ($supportedLanguages as $lang) {
                self::clearCaches($lang);
            }
        }
    }

    /**
     * Clear prompt-specific caches when AI Assistant prompts are updated
     * This method should be called from Orchid Admin Panel when prompts are modified
     */
    public static function clearPromptCaches()
    {
        // Clear all translation caches as they include prompts
        self::clearCaches();
        
        // Also clear any additional prompt-specific caches if needed
        // (Currently prompts are cached as part of translations)
    }
}
