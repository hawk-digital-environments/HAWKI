<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ApiFormat;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class ProviderBasicInfoLayout extends Rows
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
