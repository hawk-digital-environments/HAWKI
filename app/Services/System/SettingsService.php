<?php

namespace App\Services\System;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LocalizationController;

class SettingsService
{
    public function render()
    {
        $languageController = new LanguageController;
        $translation = $languageController->getTranslation();
        $localizationController = new LocalizationController;
        $localizedTexts = $localizationController->getAllLocalizedContent();
        $langs = $languageController->getAvailableLanguages();

        return view('partials/settings', compact('translation', 'langs', 'localizedTexts'));
    }
}
