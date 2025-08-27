<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\DateRange;
use Orchid\Screen\Fields\Select;

class LanguageModelDateFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Date Range';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['date_field', 'date_range'];
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
        $dateField = $this->request->get('date_field');
        $dateRange = $this->request->get('date_range');
        
        if (!$dateField || !$dateRange) {
            return $builder;
        }

        // Validate date field
        if (!in_array($dateField, ['created_at', 'updated_at'])) {
            return $builder;
        }

        // Parse date range
        if (isset($dateRange['start']) && $dateRange['start']) {
            $builder->whereDate($dateField, '>=', $dateRange['start']);
        }

        if (isset($dateRange['end']) && $dateRange['end']) {
            $builder->whereDate($dateField, '<=', $dateRange['end']);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('date_field')
                ->options([
                    'created_at' => 'Created Date',
                    'updated_at' => 'Updated Date',
                ])
                ->empty('Select Date Field')
                ->value($this->request->get('date_field'))
                ->title('Date Field'),

            DateRange::make('date_range')
                ->title('Date Range')
                ->value($this->request->get('date_range')),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $dateField = $this->request->get('date_field');
        $dateRange = $this->request->get('date_range');
        
        if (!$dateField || !$dateRange) {
            return $this->name().': All';
        }

        $fieldNames = [
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];

        $fieldName = $fieldNames[$dateField] ?? $dateField;
        
        $start = $dateRange['start'] ?? null;
        $end = $dateRange['end'] ?? null;
        
        if ($start && $end) {
            return $this->name().': '.$fieldName.' ('.$start.' - '.$end.')';
        } elseif ($start) {
            return $this->name().': '.$fieldName.' (from '.$start.')';
        } elseif ($end) {
            return $this->name().': '.$fieldName.' (until '.$end.')';
        }
        
        return $this->name().': '.$fieldName;
    }
}
