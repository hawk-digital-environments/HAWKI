<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class LanguageModelVisibleFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Visibility';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['is_visible'];
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
        $isVisible = $this->request->get('is_visible');
        
        if ($isVisible === null || $isVisible === '') {
            return $builder;
        }

        return $builder->where('is_visible', $isVisible === '1');
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('is_visible')
                ->options([
                    '1' => 'Visible',
                    '0' => 'Hidden',
                ])
                ->empty('All Visibility')
                ->value($this->request->get('is_visible'))
                ->title('Visibility'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $isVisible = $this->request->get('is_visible');
        $statusText = [
            '1' => 'Visible',
            '0' => 'Hidden',
        ];
        
        return $this->name().': '.($statusText[$isVisible] ?? 'All');
    }
}
