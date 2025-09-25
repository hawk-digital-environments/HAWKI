<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ProviderAdvancedSettingsLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            TextArea::make('provider.additional_settings')
                ->title('Additional Settings (JSON)')
                ->rows(5)
                ->help('Additional configuration in JSON format (optional)'),
        ];
    }
}
