<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class LanguageModelBasicInfoLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('model.provider.provider_name')
                ->title('Provider')
                ->readonly()
                ->help('The API provider for this model'),

            Input::make('model.model_id')
                ->title('Model ID')
                ->readonly()
                ->help('The unique identifier from the provider'),

            Input::make('model.label')
                ->title('Display Name')
                ->required()
                ->help('The display name shown in the interface'),
        ];
    }
}
