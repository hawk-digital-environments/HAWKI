<?php

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class LocalizedTextBasicLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
            return [
                Label::make('localizedText.content_key')
                    ->title('Content Key')
                    ->class('badge bg-secondary fs-5'),

                Label::make('localizedText.description')
                    ->class('fs-5 fw-bold')
                    ->style('white-space: normal; word-wrap: break-word; max-width: 100%;'),
            ];
        
    }
}
