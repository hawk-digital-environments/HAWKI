<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class MailTemplateEditLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        // Check if we're in edit mode by checking if type is already set
        $isEdit = ! empty($this->query->get('mailTemplate')['type']);

        if ($isEdit) {
            // Edit mode: Show readonly label for template type and description
            return [
                Label::make('mailTemplate.type')
                    ->title('Template Type')
                    ->class('badge bg-secondary fs-5'),

                Label::make('mailTemplate.description')
                    ->title('Description')
                    ->class('badge bg-secondary fs-5'),
            ];
        } else {
            // Create mode: Show editable input for template type
            return [
                Input::make('mailTemplate.type')
                    ->title('Template Type')
                    ->placeholder('e.g., user_registration, password_reset')
                    ->help('Unique identifier for this mail template')
                    ->required(),

                Input::make('mailTemplate.description')
                    ->title('Description')
                    ->placeholder('Brief description of this template')
                    ->help('Optional description for this mail template'),
            ];
        }
    }
}
