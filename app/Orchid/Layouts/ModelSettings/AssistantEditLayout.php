<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\User;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class AssistantEditLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            // Basic Information Section
            Input::make('assistant.name')
                ->title('Assistant Name')
                ->placeholder('Enter assistant name')
                ->help('The display name for this AI assistant')
                ->required()
                ->horizontal(),

            Input::make('assistant.key')
                ->title('Assistant Key')
                ->placeholder('e.g., support_bot, research_assistant')
                ->help('Unique identifier using lowercase letters, numbers, and underscores only')
                ->required()
                ->horizontal(),

            TextArea::make('assistant.description')
                ->title('Description')
                ->placeholder('Describe what this assistant does...')
                ->help('Brief description of the assistant\'s purpose and capabilities')
                ->rows(3)
                ->horizontal(),

            // Status and Visibility Section
            Select::make('assistant.status')
                ->title('Status')
                ->options([
                    'draft' => 'Draft',
                    'active' => 'Active',
                    'archived' => 'Archived',
                ])
                ->help('Current status of the assistant')
                ->required()
                ->horizontal(),

            Select::make('assistant.visibility')
                ->title('Visibility')
                ->options([
                    'private' => 'Private',
                    'org' => 'Organization',
                    'public' => 'Public',
                ])
                ->help('Who can access this assistant')
                ->required()
                ->horizontal(),

            // Owner Selection
            Select::make('assistant.owner_id')
                ->title('Owner')
                ->fromQuery(User::where('isRemoved', false)->orderBy('name'), 'name', 'id')
                ->help('The user responsible for this assistant')
                ->required()
                ->horizontal(),

            Input::make('assistant.org_id')
                ->title('Organization ID')
                ->placeholder('Optional UUID for organization-specific access')
                ->help('Leave empty for global access within visibility scope')
                ->horizontal(),

            // AI Model Configuration Section
            Select::make('ai_model')
                ->title('AI Model')
                ->fromQuery(AiModel::where('is_active', true), 'label', 'system_id')
                ->empty('Select AI Model')
                ->help('Choose the AI model for this assistant'),

            // System Prompt Configuration
            Select::make('assistant.prompt')
                ->title('System Prompt Type')
                ->fromQuery(
                    \App\Models\AiAssistantPrompt::select('prompt_type')
                        ->distinct()
                        ->orderBy('prompt_type'),
                    'prompt_type',
                    'prompt_type'
                )
                ->empty('Select a Prompt Type')
                ->help('Choose the system prompt template for this assistant')
                ->horizontal(),

            // Current Prompt Preview (Read-only)
            TextArea::make('currentPromptText')
                ->title('Current Prompt Preview (German)')
                ->help('Preview of the selected prompt (read-only). To edit prompts, use the System Prompts management.')
                ->readonly()
                ->rows(6)
                ->horizontal(),

            // Tools Configuration (for future use)
            TextArea::make('assistant.tools')
                ->title('Tools Configuration')
                ->placeholder('[]')
                ->help('JSON array of available tools (currently not implemented)')
                ->rows(3)
                ->horizontal(),
        ];
    }
}