<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class PromptContentLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            TextArea::make('prompt.content')
                ->title('Prompt Content')
                ->placeholder('Enter the actual prompt text that will be used by the AI assistant...')
                ->help('The complete prompt text. Use clear, specific instructions for best AI performance.')
                ->rows(12)
                ->required(),
        ];
    }
}