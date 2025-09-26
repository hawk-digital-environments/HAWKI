<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class AssistantsTabMenu extends TabMenu
{
    /**
     * Get the menu items for the Assistants management tabs.
     *
     * @return Menu[]
     */
    protected function navigations(): array
    {
        return [
            Menu::make('Assistants')
                ->route('platform.models.assistants')
                ->active('platform.models.assistants*'),

            Menu::make('Prompts')
                ->route('platform.models.prompts')
                ->active('platform.models.prompts*'),

            Menu::make('Tools')
                ->route('platform.models.tools')
                ->active('platform.models.tools*'),
        ];
    }
}