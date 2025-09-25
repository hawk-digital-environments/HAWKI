<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class SystemSettingsTabMenu extends TabMenu
{
    /**
     * Get the menu items for the system settings tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('System')
                ->route('platform.settings.system')
                ->active('platform.settings.system*'),

            Menu::make('Authentication')
                ->route('platform.settings.authentication')
                ->active('platform.settings.authentication*'),

            Menu::make('WebSockets')
                ->route('platform.settings.websockets')
                ->active('platform.settings.websockets*'),

            Menu::make('API')
                ->route('platform.settings.api')
                ->active('platform.settings.api*'),

            Menu::make('Mail Configuration')
                ->route('platform.settings.mail-configuration')
                ->active('platform.settings.mail-configuration*'),
        ];
    }
}
