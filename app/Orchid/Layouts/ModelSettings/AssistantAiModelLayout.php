<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use App\Models\AiModel;
use App\Models\ApiProvider;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class AssistantAiModelLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            // AI Model Configuration Section
            Select::make('ai_provider_filter')
                ->title('AI Provider (Filter)')
                ->fromQuery(ApiProvider::whereHas('aiModels', function($query) {
                    $query->where('is_active', true);
                })->orderBy('provider_name'), 'provider_name', 'id')
                ->empty('All Providers')
                ->help('Filter models by provider (optional)'),

            Select::make('assistant.ai_model')
                ->title('AI Model')
                ->fromQuery(AiModel::where('is_active', true)->with('provider'), 'label', 'system_id')
                ->empty('Select AI Model')
                ->help('Choose the AI model for this assistant'),

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

            // Current Prompt Preview (Read-only)
            TextArea::make('currentPromptText')
                ->title('Current Prompt Preview (German)')
                ->help('Preview of the selected prompt (read-only). To edit prompts, use the System Prompts management.')
                ->readonly()
                ->rows(6),
        ];
    }
}