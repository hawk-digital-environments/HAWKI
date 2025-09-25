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
        // Load the language files
        $translation = $this->fetchTranslationFiles($language['id']);

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

    private function fetchTranslationFiles($prefix)
    {
        // Use Laravel Cache to cache the translations
        return Cache::remember("translations_{$prefix}", now()->addHours(1), function () use ($prefix) {
            return AppSystemText::where('language', $prefix)
                ->get()
                ->pluck('content', 'content_key')
                ->toArray();
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
     * This method clears translation caches but doesn't handle system text model caches directly,
     * as system texts are typically cached at the application level.
     */
    public static function clearCaches(?string $language = null)
    {
        if ($language) {
            // Clear specific language caches
            Cache::forget("translations_{$language}");
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
}
