<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Fields\BadgeField;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class AssistantBasicInfoLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        $assistant = $this->query->get('assistant');
        $isNew = !$assistant || !$assistant->exists;
        
        $fields = [];

        // Assistant Key - editable for new, badge for existing (First field)
        if ($isNew) {
            $fields[] = Input::make('assistant.key')
                ->title('Assistant Key')
                ->placeholder('e.g., support_bot, research_assistant')
                ->help('Unique identifier using lowercase letters, numbers, and underscores only')
                ->required();
        } else {
            $fields[] = BadgeField::make('assistant.key')
                ->title('Assistant Key')
                ->help('Unique identifier')
                ->badgeClass('bg-info-subtle text-info-emphasis');
        }

        $fields = array_merge($fields, [
            // Basic Information Section
            Input::make('assistant.name')
                ->title('Assistant Name')
                ->placeholder('Enter assistant name')
                ->help('The display name for this AI assistant')
                ->required(),
        ]);

        $fields = array_merge($fields, [
            TextArea::make('assistant.description')
                ->title('Description')
                ->placeholder('Describe what this assistant does...')
                ->help('Brief description of the assistant\'s purpose and capabilities')
                ->rows(3),
        ]);

        return $fields;
    }
}