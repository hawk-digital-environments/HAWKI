<?php

namespace App\Orchid\Layouts\AccessControls;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class AccessControlsTabMenu extends TabMenu
{
    /**
     * Get the menu items for the access controls tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('Roles')
                ->route('platform.systems.roles')
                ->active('platform.systems.roles*'),

            Menu::make('Role Assignments')
                ->route('platform.role-assignments')
                ->active('platform.role-assignments*'),
        ];
    }
}
