<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AssistantDefaultPromptLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            // System Prompt Configuration
            Select::make('assistant.prompt')
                ->title('System Prompt Type')
                ->fromQuery(
                    AiAssistantPrompt::select('title')
                        ->distinct()
                        ->orderBy('title'),
                    'title',
                    'title'
                )
                ->empty('Select a Prompt Type')
                ->help('Choose the system prompt template for this assistant'),
        ];
    }
}