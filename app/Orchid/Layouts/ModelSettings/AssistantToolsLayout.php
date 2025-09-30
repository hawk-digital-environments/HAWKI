<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class AssistantToolsLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        return [
            // Tools Configuration (for future use)
            TextArea::make('assistant.tools')
                ->title('Tools Configuration')
                ->placeholder('[]')
                ->help('JSON array of available tools (currently not implemented)')
                ->rows(3),
        ];
    }
}