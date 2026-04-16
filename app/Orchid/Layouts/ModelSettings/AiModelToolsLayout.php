<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Layouts\Rows;

class AiModelToolsLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            CheckBox::make('model.settings.tools.file_upload')
                ->title('File Upload')
                ->help('Enable file upload and processing capabilities')
                ->sendTrueOrFalse()
                ->placeholder('Support file uploads'),

            CheckBox::make('model.settings.tools.vision')
                ->title('Vision')
                ->help('Enable image/vision processing capabilities')
                ->sendTrueOrFalse()
                ->placeholder('Support image analysis'),

            CheckBox::make('model.settings.tools.web_search')
                ->title('Web Search')
                ->help('Enable web search integration (requires provider support)')
                ->sendTrueOrFalse()
                ->placeholder('Allow web searches'),

            CheckBox::make('model.settings.tools.reasoning')
                ->title('Reasoning')
                ->help('Enable advanced reasoning and chain-of-thought capabilities')
                ->sendTrueOrFalse()
                ->placeholder('Support advanced reasoning'),
        ];
    }
}
