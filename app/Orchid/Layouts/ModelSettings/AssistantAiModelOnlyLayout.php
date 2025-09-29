<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiModel;
use App\Models\ApiProvider;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AssistantAiModelOnlyLayout extends Rows
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
        ];
    }
}