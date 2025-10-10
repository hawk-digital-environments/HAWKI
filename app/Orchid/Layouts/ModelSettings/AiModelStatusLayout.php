<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class AiModelStatusLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Switcher::make('model.is_active')
                ->title('Active')
                ->sendTrueOrFalse()
                ->help('Whether this model is available for use'),

            Switcher::make('model.is_visible')
                ->title('Visible')
                ->sendTrueOrFalse()
                ->help('Whether this model appears in user interfaces'),
        ];
    }
}
