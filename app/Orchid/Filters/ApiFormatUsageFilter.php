<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ApiFormatUsageFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Usage Status';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['usage_status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $usage = $this->request->get('usage_status');

        if (empty($usage)) {
            return $builder;
        }

        switch ($usage) {
            case 'used':
                return $builder->whereHas('providers');
            case 'unused':
                return $builder->whereDoesntHave('providers');
            default:
                return $builder;
        }
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('usage_status')
                ->options([
                    '' => 'All Formats',
                    'used' => 'Currently Used',
                    'unused' => 'Not Used',
                ])
                ->value($this->request->get('usage_status'))
                ->title('Usage Status')
                ->empty('All Formats'),
        ];
    }
}
