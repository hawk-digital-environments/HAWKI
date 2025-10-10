<?php

namespace App\Http\Controllers;

class SettingsController extends Controller
{
    // / Render Settings Panel
    public function initialize()
    {
        $languageController = new LanguageController;
        $translation = $languageController->getTranslation();
        $localizationController = new LocalizationController;
        $localizedTexts = $localizationController->getAllLocalizedContent();

        $langs = $languageController->getAvailableLanguages();

        return view('partials/settings', compact('translation', 'langs', 'localizedTexts'));
    }
}
