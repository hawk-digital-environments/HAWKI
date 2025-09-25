<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ApiFormatSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Search API Formats';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['api_format_search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('api_format_search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search) {
            $query->where('display_name', 'LIKE', "%{$search}%")
                ->orWhere('unique_name', 'LIKE', "%{$search}%")
                ->orWhere('base_url', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('api_format_search')
                ->type('search')
                ->value($this->request->get('api_format_search'))
                ->placeholder('Search by display name, unique name, or base URL...')
                ->title('Search'),
        ];
    }
}
