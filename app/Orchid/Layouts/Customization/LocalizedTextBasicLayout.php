<?php

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class LocalizedTextBasicLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        // Check if we're in edit mode by checking if content_key is already set
        $isEdit = ! empty($this->query->get('localizedText')['content_key']);

        if ($isEdit) {
            // Edit mode: Show readonly labels
            return [
                Label::make('localizedText.content_key')
                    ->title('Content Key')
                    ->class('badge bg-secondary fs-5'),

                Label::make('localizedText.description')
                    ->title('Description')
                    ->class('badge bg-secondary fs-5'),
            ];
        } else {
            // Create mode: Show editable inputs
            return [
                Input::make('localizedText.content_key')
                    ->title('Content Key')
                    ->placeholder('e.g., welcome.message, header.title')
                    ->help('Unique identifier for this localized text')
                    ->required(),

                Input::make('localizedText.description')
                    ->title('Description')
                    ->placeholder('Brief description of where this text is used')
                    ->help('Optional description for this localized text'),
            ];
        }
    }
}
