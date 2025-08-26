<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class ProviderStatusLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Switcher::make('provider.is_active')
                ->title('Active')
                ->sendTrueOrFalse()
                ->help('Enable this provider for use in the application'),
        ];
    }
}
