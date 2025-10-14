<?php

namespace App\Services\System;

use App\Http\Controllers\LanguageController;


class SettingsService{
    public function render()
    {
        $languageController = new LanguageController;
        $translation = $languageController->getTranslation();
        $langs = $languageController->getAvailableLanguages();

        return view('partials/settings', compact('translation', 'langs'));
    }
}
