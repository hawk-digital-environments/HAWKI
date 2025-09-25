<?php

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class LocalizedTextContentLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            TextArea::make('localizedText.de_content')
                ->title('German Content (de_DE)')
                ->placeholder('Enter German text content')
                ->rows(6)
                ->help('German translation of this localized text')
                ->resizeable(true),

            TextArea::make('localizedText.en_content')
                ->title('English Content (en_US)')
                ->placeholder('Enter English text content')
                ->rows(6)
                ->help('English translation of this localized text'),
        ];
    }
}
