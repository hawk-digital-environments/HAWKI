<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class LanguageModelInformationLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            TextArea::make('informationJson')
                ->title('Model Information (Read-Only)')
                ->help('Technical information and capabilities provided by the API provider')
                ->rows(25)
                ->readonly()
                ->style('font-family: monospace; resize: vertical;'),
        ];
    }
}
