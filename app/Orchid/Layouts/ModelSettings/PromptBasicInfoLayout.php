<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class PromptBasicInfoLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            Input::make('prompt.title')
                ->title('Prompt Title')
                ->placeholder('Enter a descriptive title for this prompt')
                ->help('A clear, descriptive name for this prompt template')
                ->required(),

            TextArea::make('prompt.description')
                ->title('Description')
                ->placeholder('Describe the purpose and usage of this prompt...')
                ->help('Optional description explaining when and how to use this prompt')
                ->rows(3),
        ];
    }
}