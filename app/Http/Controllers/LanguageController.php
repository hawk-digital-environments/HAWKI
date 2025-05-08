<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use App\Models\AppLocalizedText;
use App\Models\AppSystemText;

class LanguageController extends Controller
{
    /// Changes the language based on the previous values or the default parameters as fallback
    public function getTranslation()
    {
        $langs = config('locale.langs');
        //LANGUAGE CHANGE...
        if (Session::has('language')) {
            $language = Session::get('language');
        } else {
            // try to get cookie from last use
            if (Cookie::get('lastLanguage_cookie') && Cookie::get('lastLanguage_cookie') != '') {
                $language = $langs[Cookie::get('lastLanguage_cookie')];
            } else {
                // If there's not a cookie, try the default language from config or set a hardcoded default
                $language = $langs[config('locale.default_language')];
            }
        }
        if(gettype($language) == 'string'){
            $langs[config($lang)];
        }

        // Store the language in session
        Session::put('language', $language);
        // Load the language files
        $translation = $this->fetchTranslationFiles($language['id']);
        
        // Process placeholders in all translations
        $translation = $this->processAllPlaceholders($translation, $language['id']);

        return $translation;
    }

    /// Change language to the request language
    public function changeLanguage(Request $request)
    {
        $validatedData = $request->validate([
            'inputLang' => 'required|string|size:5',
        ]);
        $langId = $validatedData['inputLang'];

        $langs = config('locale.langs');
        $language = $langs[$langId];
        
        if (!$language) {
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

    /// return array of languages
    public function getAvailableLanguages(){
        $languages = config('locale')['langs'];
        $availableLocale = [];
        foreach($languages as $lang){
            if($lang['active']){
                array_push($availableLocale, $lang);
            }
        }
        return $availableLocale;
    }

    private function fetchTranslationFiles($prefix) {
        // Load system texts from the database
        $translations = AppSystemText::where('language', $prefix)
            ->get()
            ->pluck('content', 'content_key')
            ->toArray();
        
        return $translations;
    }

    /**
     * Process placeholders in all translation items
     *
     * @param array $translations Array of translations
     * @param string $language Current language code (e.g. 'de_DE')
     * @return array
     */
    protected function processAllPlaceholders(array $translations, string $language): array
    {
        // Try to get app_name from AppLocalizedText model first
        $appName = AppLocalizedText::getContent('app_name', $language);
        
        // If not found in database, check translations array
        if (!$appName && isset($translations['app_name'])) {
            $appName = $translations['app_name'];
        }
        
        // Default fallback if no app_name found
        $appName = $appName ?? 'HAWKI';
        
        // Replace :system placeholder in all string translations
        foreach ($translations as $key => $value) {
            if (is_string($value)) {
                $translations[$key] = str_replace(':system', $appName, $value);
            }
        }
        
        return $translations;
    }
}
