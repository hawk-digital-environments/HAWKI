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
        if ($request->hasHeader('X-App-Locale')) {
            $requestLocale = $this->localeService->getMostLikelyLocale($request->header('X-App-Locale'));
            $this->localeService->setCurrentLocale($requestLocale, false);
        } else if ($request->query('lang')) {
            $requestLocale = $this->localeService->getMostLikelyLocale($request->query('lang'));
            $this->localeService->setCurrentLocale($requestLocale, true);
        }
        
        return $next($request);
    }
}
