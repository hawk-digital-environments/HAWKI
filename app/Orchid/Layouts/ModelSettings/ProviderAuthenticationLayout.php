<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class ProviderAuthenticationLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('provider.api_key')
                ->title('API Key')
                ->type('password')
                ->help('Authentication key for the API'),
        ];
    }
}
