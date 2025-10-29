<?php

namespace App\Services\Translation\View;

use App\Services\Translation\LocaleService;
use Illuminate\View\Component;

/**
 * Renders the current locale as a JSON object.
 */
class CurrentLocaleJsonComponent extends Component
{
    public function __construct(
        private readonly LocaleService $localeService
    )
    {
    }
    
    public function render(): string
    {
        return json_encode($this->localeService->getCurrentLocale()->toLegacyArray(), JSON_THROW_ON_ERROR);
    }
}
