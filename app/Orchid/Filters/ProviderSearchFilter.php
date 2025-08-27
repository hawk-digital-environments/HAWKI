<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ProviderSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Search';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['search'];
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
        $search = $this->request->get('search');
        
        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search) {
            $query->where('provider_name', 'like', "%{$search}%")
                  ->orWhereHas('apiFormat', function (Builder $subQuery) use ($search) {
                      $subQuery->where('unique_name', 'like', "%{$search}%")
                               ->orWhere('display_name', 'like', "%{$search}%")
                               ->orWhere('base_url', 'like', "%{$search}%");
                  });
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('search')
                ->type('search')
                ->value($this->request->get('search'))
                ->placeholder('Search providers...')
                ->title('Search'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('search');
    }
}
