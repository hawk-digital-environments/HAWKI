<?php

namespace App\Services\Frontend\View;

use App\Services\Translation\LocaleService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Blade component `<x-settings-panel />` that renders the global settings panel.
 *
 * Passes the list of available UI locales to the `components.settings-panel` view so
 * users can switch language without a page reload.
 */
class SettingsPanelComponent extends Component
{
    public function __construct(
        private LocaleService $localeService
    )
    {
    }

    public function render(): View
    {
        return view(
            'components.settings-panel',
            ['langs' => $this->localeService->getAvailableLocales()]
        );
    }
}
