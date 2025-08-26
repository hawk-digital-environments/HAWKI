<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class ApiManagementTabMenu extends TabMenu
{
    /**
     * Get the menu items for the API management tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('Providers')
                ->route('platform.models.api.providers')
                ->active('platform.models.api.providers*'),

            Menu::make('API-Formats')
                ->route('platform.models.api.formats')
                ->active('platform.models.api.formats*'),
        ];
    }
}
