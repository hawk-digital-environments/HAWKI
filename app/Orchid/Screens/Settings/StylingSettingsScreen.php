<?php

namespace App\Orchid\Screens\Settings;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

use Orchid\Screen\Fields\Code;

class StylingSettingsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Styling Settings';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::rows([
                Code::make('css')
                    ->language('css')
                    ->title('CSS Editor')
                    ->value('/* CSS hier laden oder ändern */'),
            ]),
        ];
    }
}
