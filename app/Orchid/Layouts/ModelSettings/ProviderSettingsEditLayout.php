<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ProviderSetting;
use App\Models\ApiFormat;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ProviderSettingsEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('provider.provider_name')
                ->title('Provider Name')
                ->required()
                ->help('Unique name for this provider'),

            Select::make('provider.api_format_id')
                ->title('API Format')
                ->options($this->getApiFormatOptions())
                ->required()
                ->help('The API interface format to use'),

            Input::make('provider.api_key')
                ->title('API Key')
                ->type('password')
                ->help('Authentication key for the API'),

            Switcher::make('provider.is_active')
                ->title('Active')
                ->sendTrueOrFalse()
                ->help('Enable this provider for use in the application'),

            TextArea::make('provider.additional_settings')
                ->title('Additional Settings (JSON)')
                ->rows(5)
                ->help('Additional configuration in JSON format (optional)'),
        ];
    }

    /**
     * Get available API format options from database
     *
     * @return array
     */
    private function getApiFormatOptions(): array
    {
        return ApiFormat::all()
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
