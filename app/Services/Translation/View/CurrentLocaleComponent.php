<?php

namespace App\Services\Translation\View;

use App\Services\Translation\LocaleService;
use Illuminate\View\Component;

/**
 * Renders the current locale's language code.
 */
class CurrentLocaleComponent extends Component
{
    public function __construct(
        private readonly LocaleService $localeService
    )
    {
    }
    
    public function render(): string
    {
        return $this->localeService->getCurrentLocale()->lang;
    }
}
