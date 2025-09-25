<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class SystemImageSearchFilter extends Filter
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
        return $builder->where(function (Builder $query) {
            $query->where('name', 'like', '%'.$this->request->get('search').'%')
                ->orWhere('original_name', 'like', '%'.$this->request->get('search').'%')
                ->orWhere('mime_type', 'like', '%'.$this->request->get('search').'%');
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('search')
                ->type('text')
                ->value($this->request->get('search'))
                ->placeholder('Search by name, filename or format...')
                ->title('Search System Images'),
        ];
    }
}
