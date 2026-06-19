<?php

namespace App\Http\Middleware;

use App\Services\Translation\LocaleService;
use Closure;
use Illuminate\Http\Request;

/**
 * Allows setting the application locale via the X-App-Locale header.
 */
readonly class LocaleSettingMiddleware
{
    public function __construct(
        private LocaleService $localeService
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $localeValue = $request->header('X-App-Locale') ?? $request->query('lang');
        if (!empty($localeValue)) {
            $requestLocale = $this->localeService->getMostLikelyLocale($localeValue);
            $this->localeService->setCurrentLocale($requestLocale);
        } else {
            $requestLocale = $this->localeService->getCurrentLocale();
        }

        $request->setLocale(strtolower($requestLocale->shortName));

        return $next($request);
    }
}
