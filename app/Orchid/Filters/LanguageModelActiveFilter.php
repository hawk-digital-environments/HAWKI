<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class LanguageModelActiveFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Active Status';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['is_active'];
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
        $isActive = $this->request->get('is_active');
        
        if ($isActive === null || $isActive === '') {
            return $builder;
        }

        return $builder->where('is_active', $isActive === '1');
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('is_active')
                ->options([
                    '1' => 'Active',
                    '0' => 'Inactive',
                ])
                ->empty('All Active Status')
                ->value($this->request->get('is_active'))
                ->title('Active Status'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $isActive = $this->request->get('is_active');
        $statusText = [
            '1' => 'Active',
            '0' => 'Inactive',
        ];
        
        return $this->name().': '.($statusText[$isActive] ?? 'All');
    }
}
