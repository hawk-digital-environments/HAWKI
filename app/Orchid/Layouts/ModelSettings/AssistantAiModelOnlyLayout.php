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
            Select::make('assistant.ai_model')
                ->title('AI Model')
                ->fromQuery(AiModel::where('is_active', true)->with('provider'), 'label', 'system_id')
                ->empty('Select AI Model')
                ->help('Choose the AI model for this assistant'),
        ];
    }
}