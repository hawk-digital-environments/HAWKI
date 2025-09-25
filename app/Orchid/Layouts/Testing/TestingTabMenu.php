<?php

namespace App\Orchid\Layouts\Testing;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class TestingTabMenu extends TabMenu
{
    /**
     * Get the menu items for the testing tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('Testing Settings')
                ->route('platform.testing.settings')
                ->active('platform.testing.settings*'),

            Menu::make('Mail Testing')
                ->route('platform.testing.mail')
                ->active('platform.testing.mail*'),
        ];
    }
}
