<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class CustomizationTabMenu extends TabMenu
{
    /**
     * Get the menu elements to be displayed.
     */
    protected function navigations(): iterable
    {
        return [
            Menu::make('Localized Text')
                ->route('platform.customization.localizedtexts')
                ->active('platform.customization.localizedtexts*'),

            Menu::make('System Text')
                ->route('platform.customization.systemtexts')
                ->active('platform.customization.systemtexts*'),

                Menu::make('Announcements')
                ->route('platform.customization.announcements')
                ->active('platform.customization.announcements*'),

            Menu::make('Mail Templates')
                ->route('platform.customization.mailtemplates')
                ->active('platform.customization.mailtemplates*'),

            Menu::make('System Images')
                ->route('platform.customization.systemimages')
                ->active('platform.customization.systemimages*'),

            Menu::make('CSS Rules')
                ->route('platform.customization.css')
                ->active('platform.customization.css*'),
        ];
    }
}
