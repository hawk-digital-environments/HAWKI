<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\DateRange;

class AiModelDateFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Date Range';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['date_range'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $dateRange = $this->request->get('date_range');

        if (empty($dateRange) || ! is_array($dateRange)) {
            return $builder;
        }

        $startDate = $dateRange['start'] ?? null;
        $endDate = $dateRange['end'] ?? null;

        if ($startDate) {
            $builder->where('ai_models.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $builder->where('ai_models.created_at', '<=', $endDate);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            DateRange::make('date_range')
                ->title('Created Date Range')
                ->value($this->request->get('date_range')),
        ];
    }

    /**
     * Get the display value for the filter.
     */
    public function value(): string
    {
        $dateRange = $this->request->get('date_range');
        
        if (empty($dateRange) || !is_array($dateRange)) {
            return 'All Dates';
        }

        $startDate = $dateRange['start'] ?? null;
        $endDate = $dateRange['end'] ?? null;

        if ($startDate && $endDate) {
            return $startDate . ' to ' . $endDate;
        } elseif ($startDate) {
            return 'From ' . $startDate;
        } elseif ($endDate) {
            return 'Until ' . $endDate;
        }

        return 'All Dates';
    }
}
