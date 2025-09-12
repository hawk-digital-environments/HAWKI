<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class LogManagementTabMenu extends TabMenu
{
    /**
     * Get the menu items for the log management sub-tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('System Log')
                ->route('platform.settings.log.system')
                ->active('platform.settings.log.system*'),

            Menu::make('Configuration')
                ->route('platform.settings.log.configuration')
                ->active('platform.settings.log.configuration*'),
        ];
    }
}
