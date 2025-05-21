<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LocalizationController;


class SettingsController extends Controller
{
    /// Render Settings Panel
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
