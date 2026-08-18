<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Translation\Exception\SettingUnavailableLocaleException;
use App\Services\Translation\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class ConnectionController extends Controller
{
    use Actions\FetchOne;

    /**
     * Persists the client's preferred locale (session + long-lived cookie), so
     * both the SPA and server-rendered pages use it on subsequent requests.
     */
    public function storeLocale(Request $request, LocaleService $localeService): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:5',
        ]);

        try {
            $localeService->setCurrentLocale($validated['locale'], true);
        } catch (SettingUnavailableLocaleException) {
            abort(422, 'The requested locale is not available.');
        }

        return response()->json([
            'locale' => $localeService->getCurrentLocale()->lang,
        ]);
    }
}
