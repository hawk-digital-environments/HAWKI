<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Layouts\Rows;

class ApiFormatEndpointsLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            Matrix::make('endpoints')
                ->title('API Endpoints')
                ->columns([
                    'Name',
                    'Path',
                    'Method',
                    'Is Active',
                ])
                ->help('Define the available endpoints for this API format. Add rows as needed.'),
        ];
    }
}
