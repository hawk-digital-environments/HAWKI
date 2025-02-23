<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\LanguageController;

class SettingsController extends Controller
{
    /// Render Settings Panel
    public function initialize()
    {
        $languageController = new LanguageController;
        $translation = $languageController->getTranslation();
        $langs = $languageController->getAvailableLanguages();
        
        return view('partials/settings', compact('translation', 'langs'));
    }
}
