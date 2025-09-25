<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class LocalizedTextSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Search';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function ($query) use ($search) {
            $query->where('content_key', 'LIKE', "%{$search}%")
                ->orWhere('content', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    /**
     * The displayable fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('search')
                ->type('search')
                ->value($this->request->get('search'))
                ->placeholder('Search content keys, text, or descriptions...')
                ->title('Search'),
        ];
    }
}
