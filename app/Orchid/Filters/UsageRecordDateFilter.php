<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateRange;

class UsageRecordDateFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Created At';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['created_from', 'created_to'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $createdFrom = $this->request->get('created_from');
        $createdTo = $this->request->get('created_to');

        if ($createdFrom) {
            $builder->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo) {
            $builder->whereDate('created_at', '<=', $createdTo);
        }

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
            DateRange::make('created_from')
                ->title('From')
                ->value($this->request->get('created_from')),

            DateRange::make('created_to')
                ->title('To')
                ->value($this->request->get('created_to')),
        ];
    }
}
