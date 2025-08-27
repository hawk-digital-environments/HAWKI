<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class LanguageModelSettingsLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            TextArea::make('settingsJson')
                ->title('Model Settings (JSON)')
                ->help('Configure the settings for this model in JSON format')
                ->rows(15)
                ->style('font-family: monospace; resize: vertical;'),
        ];
    }
}
