<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class PromptMetaLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        $prompt = $this->query->get('prompt');
        
        return [
            Input::make('prompt_title')
                ->title('Prompt Title')
                ->placeholder('Enter a descriptive title for this prompt')
                ->help('This title will be used for both language versions')
                ->value($prompt ? $prompt->title : '')
                ->required(),

            TextArea::make('prompt_description')
                ->title('Description')
                ->placeholder('Describe the purpose and usage of this prompt...')
                ->help('Optional description explaining when and how to use this prompt')
                ->value($prompt ? $prompt->description : '')
                ->rows(2),

            Select::make('prompt.category')
                ->title('Category')
                ->options([
                    'general' => 'General',
                    'system' => 'System',
                    'utility' => 'Utility',
                    'template' => 'Template',
                    'custom' => 'Custom',
                    'creative' => 'Creative',
                    'analytical' => 'Analytical',
                    'conversational' => 'Conversational',
                ])
                ->help('Categorize this prompt for better organization')
                ->required(),
        ];
    }
}