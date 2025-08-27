<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class LanguageModelTabMenu extends TabMenu
{
    /**
     * Get the menu items for the language model management tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('Model List')
                ->route('platform.models.language')
                ->active('platform.models.language*'),
        ];
    }
}
