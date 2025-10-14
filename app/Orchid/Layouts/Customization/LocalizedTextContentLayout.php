<?php

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Quill;

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
            Quill::make('localizedText.de_content')
                ->title('German Content (de_DE)')
                ->placeholder('Enter German text content')
                ->help('German translation of this localized text')
                ->class('form-control'),

            Quill::make('localizedText.en_content')
                ->title('English Content (en_US)')
                ->placeholder('Enter English text content')
                ->help('English translation of this localized text')
                ->class('form-control'),
        ];
    }
}
