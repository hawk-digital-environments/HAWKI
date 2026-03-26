<?php

namespace App\Services\System;


readonly class SettingsService
{

    public function render()
    {
        return view('partials/settings', ['langs' => $this->localeService->getAvailableLocales()]);
    }
}
