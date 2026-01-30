<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Layouts\Rows;

class BackupFilterLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return iterable
     */
    protected function fields(): iterable
    {
        return [
            DateTimer::make('date_filter')
                ->title('Filter by Date')
                ->format('Y-m-d')
                ->allowInput()
                ->enableTime(false)
                ->placeholder('Select date to filter backups'),
        ];
    }
}
