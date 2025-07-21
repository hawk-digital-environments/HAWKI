<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie; // Ensure this is imported
use Illuminate\Support\Facades\Log;

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
            $langs[config($language)];
        }

        // Store the language in session
        Session::put('language', $language);
        // Load the language files
        $translation = $this->fetchTranslationFiles($language['id']);

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
        $languagePath = resource_path('language/');
        $files = scandir($languagePath);

        $translations = [];
        $defaultTranslations = [];

        // Filter and load files with the specific prefix
        foreach ($files as $file) {
            // Check if the file has the correct prefix
            if (strpos($file, $prefix) !== false) {
                $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

                if ($fileExtension === 'json') {
                    // Read JSON file as associative array
                    $fileContent = file_get_contents($languagePath . $file);
                    $translationArray = json_decode($fileContent, true);

                    if ($translationArray !== null) {
                        // Check if it's a default language file
                        if ($file === $prefix . '.json') {
                            $defaultTranslations = array_merge($defaultTranslations, $translationArray);
                        } else {
                            $translations = array_merge($translations, $translationArray);
                        }
                    }
                } elseif ($fileExtension === 'html') {
                    // Read HTML file and create a key-value pair
                    $htmlContent = file_get_contents($languagePath . $file);
                    $baseFileName = basename($file, '_' . $prefix . '.html');
                    $keyName = '_' . $baseFileName;
                    $translations[$keyName] = $htmlContent;
                }
            }
        }

        // Merge default translations with lower priority
        $mergedTranslations = array_merge($defaultTranslations, $translations);

        return $mergedTranslations;
    }
}
