<?php

namespace App\Services\Frontend\View;

use App\Services\Translation\LocaleService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

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
