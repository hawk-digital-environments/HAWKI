<?php

namespace App\Http\Controllers;

use App\Models\AppSystemPrompt;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class AppSystemPromptController extends Controller
{
    /**
     * Returns all system prompts in the current language
     *
     * @return array
     */
    public function getDefaultPrompts()
    {
        $language = $this->getCurrentLanguage();
        $fallbackLanguage = 'en_US';

        // Retrieve all available model types
        $modelTypes = AppSystemPrompt::select('prompt_type')->distinct()->pluck('prompt_type')->toArray();

        $prompts = [];
        foreach ($modelTypes as $modelType) {
            // Try loading the prompt in the current language
            $promptText = AppSystemPrompt::getPrompt($modelType, $language);

            // Fallback to the fallback language if not found
            if (! $promptText && $language !== $fallbackLanguage) {
                $promptText = AppSystemPrompt::getPrompt($modelType, $fallbackLanguage);
            }

            if ($promptText) {
                $prompts[$modelType] = $promptText;
            }
        }

        return $prompts;
    }

    /**
     * Returns a specific system prompt by model type
     *
     * @return string|null
     */
    public function getPrompt(string $modelType)
    {
        $language = $this->getCurrentLanguage();
        $fallbackLanguage = 'en_US';

        // Try loading the prompt in the current language
        $promptText = AppSystemPrompt::getPrompt($modelType, $language);

        // Fallback to the fallback language if not found
        if (! $promptText && $language !== $fallbackLanguage) {
            $promptText = AppSystemPrompt::getPrompt($modelType, $fallbackLanguage);
        }

        return $promptText;
    }

    /**
     * Determine current language from session or app locale
     * Returns language in format like "de_DE" or "en_US"
     */
    protected function getCurrentLanguage(): string
    {
        if (Session::has('language')) {
            $language = Session::get('language');
            if (isset($language['id']) && ! empty($language['id'])) {
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
