<?php

namespace App\Http\Controllers;

use App\Services\Translation\Exception\SettingUnavailableLocaleException;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\Translator;

class LanguageController extends Controller
{
    private LocaleService $localeService;
    private Translator $translator;
    
    public function __construct(
        ?LocaleService $translationService = null,
        ?Translator    $translator = null
    )
    {
        // This service is sometimes used without Laravel's service container, so we need to allow passing the dependencies manually
        // If not provided, we resolve them from the service container
        $this->localeService = $translationService ?? app(LocaleService::class);
        $this->translator = $translator ?? app(Translator::class);
    }

    /// Change language to the request language
    public function changeLanguage(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'inputLang' => 'required|string|size:5',
        ]);
        
        try {
            $this->localeService->setCurrentLocale($validatedData['inputLang'], true); // true = persist in session and cookie
        } catch (SettingUnavailableLocaleException $e) {
            Log::warning('LanguageController: changeLanguage: Invalid language requested: ' . $validatedData['inputLang'], ['exception' => $e]);
            return response()->json(['success' => false, 'error' => 'Invalid language'], 400);
        }
        
        return response()->json([
            'success' => true,
        ]);
    }
    
    /**
     * @deprecated This method is deprecated and will be removed in future versions. Use laravel's built-in localization features instead.
     */
    public function getTranslation(): array
    {
        return $this->translator->get('*');
    }
    
    /**
     * @deprecated This method is deprecated and will be removed in future versions. Use {@see LocaleService::getAvailableLocales()} instead.
     */
    public function getAvailableLanguages(): array
    {
        return array_map(
            static fn(Locale $locale) => [
                'id' => $locale->lang,
                'name' => $locale->nameInLanguage,
                'label' => $locale->shortName
            ],
            $this->localeService->getAvailableLocales()
        );
    }
}
