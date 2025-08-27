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
     *
     * @return string
     */
    public function name(): string
    {
        return 'Usage Status';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['usage'];
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
        $usage = $this->request->get('usage');
        
        if ($usage === null || $usage === '') {
            return $builder;
        }

        switch ($usage) {
            case 'used':
                return $builder->whereHas('providerSettings');
            case 'unused':
                return $builder->whereDoesntHave('providerSettings');
            default:
                return $builder;
        }
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('usage')
                ->options([
                    'used' => 'Used by Providers',
                    'unused' => 'Not Used',
                ])
                ->empty('All Formats')
                ->value($this->request->get('usage'))
                ->title('Usage Status'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $usage = $this->request->get('usage');
        $usageText = [
            'used' => 'Used by Providers',
            'unused' => 'Not Used',
        ];
        
        return $this->name().': '.($usageText[$usage] ?? 'All');
    }
}
