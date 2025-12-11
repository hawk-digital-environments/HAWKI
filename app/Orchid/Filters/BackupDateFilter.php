<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;

class BackupDateFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Filter by Date';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['backup_date'];
    }

    /**
     * Apply to a given Eloquent query builder.
     * Note: This filter is applied manually in the BackupSettingsScreen
     * since backups are not from a database model.
     */
    public function run(Builder $builder): Builder
    {
        // This method won't be used since backups are file-based
        return $builder;
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            DateTimer::make('backup_date')
                ->title('Date')
                ->format('Y-m-d')
                ->allowInput()
                ->enableTime(false)
                ->placeholder('Select date...')
                ->value($this->request->get('backup_date')),
        ];
    }
}
