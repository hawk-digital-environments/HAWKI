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
            Input::make('provider.base_url')
                ->title('Base URL')
                ->type('url')
                ->required()
                ->autocomplete('off')
                ->set('data-form-type', 'other')
                ->help('The base URL for the API endpoint (e.g., https://api.openai.com/v1)'),

            Input::make('provider.api_key')
                ->title('API Key')
                ->type('password')
                ->autocomplete('new-password')
                ->set('data-form-type', 'other')
                ->help('Authentication key for the API (leave empty to keep existing key)'),
        ];
    }
}
