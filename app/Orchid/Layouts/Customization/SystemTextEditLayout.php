<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class SystemTextEditLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        // Check if we're in edit mode by checking if content_key is already set
        $isEdit = ! empty($this->query->get('systemText')['content_key']);

        if ($isEdit) {
            // Edit mode: Show readonly label for content_key, editable textareas for content
            return [
                Label::make('systemText.content_key')
                    ->title('Content Key')
                    ->class('badge bg-secondary fs-5'),

                TextArea::make('systemText.de_content')
                    ->title('German (de_DE)')
                    ->rows(4)
                    ->help('German translation of the system text'),

                TextArea::make('systemText.en_content')
                    ->title('English (en_US)')
                    ->rows(4)
                    ->help('English translation of the system text'),
            ];
        } else {
            // Create mode: Show editable input for content_key and textareas for content
            return [
                Input::make('systemText.content_key')
                    ->title('Content Key')
                    ->placeholder('Enter unique content key')
                    ->help('Unique identifier for this system text')
                    ->required(),

                TextArea::make('systemText.de_content')
                    ->title('German (de_DE)')
                    ->rows(4)
                    ->help('German translation of the system text'),

                TextArea::make('systemText.en_content')
                    ->title('English (en_US)')
                    ->rows(4)
                    ->help('English translation of the system text'),
            ];
        }
    }
}
