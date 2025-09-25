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
     */
    public function name(): string
    {
        return 'Search Providers';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['provider_search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('provider_search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search) {
            $query->where('provider_name', 'LIKE', "%{$search}%")
                ->orWhere('api_key', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('provider_search')
                ->type('search')
                ->value($this->request->get('provider_search'))
                ->placeholder('Search by provider name or API key...')
                ->title('Search'),
        ];
    }
}
