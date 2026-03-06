<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateRange;

class UserCreatedDateFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Created Date';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['created_date_range'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        $dateRange = $this->request->get('created_date_range');

        if (empty($dateRange) || !is_array($dateRange)) {
            return $builder;
        }

        $startDate = $dateRange['start'] ?? null;
        $endDate = $dateRange['end'] ?? null;

        if ($startDate) {
            // Start of day is fine as-is (00:00:00)
            $builder->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            // Convert end date to end of day (23:59:59)
            // Extract just the date part and append 23:59:59
            $endDateOnly = substr($endDate, 0, 10); // "2025-11-09"
            $endOfDay = $endDateOnly . ' 23:59:59';
            $builder->where('created_at', '<=', $endOfDay);
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
            DateRange::make('created_date_range')
                ->title($this->name())
                ->value($this->request->get('created_date_range')),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $dateRange = $this->request->get('created_date_range');

        if (empty($dateRange) || !is_array($dateRange)) {
            return $this->name();
        }

        $startDate = $dateRange['start'] ?? null;
        $endDate = $dateRange['end'] ?? null;

        if ($startDate && $endDate) {
            return $this->name() . ': ' . $startDate . ' - ' . $endDate;
        }

        if ($startDate) {
            return $this->name() . ': From ' . $startDate;
        }

        if ($endDate) {
            return $this->name() . ': Until ' . $endDate;
        }

        return $this->name();
    }
}
