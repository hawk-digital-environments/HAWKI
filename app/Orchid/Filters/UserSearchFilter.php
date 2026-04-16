<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class UserSearchFilter extends Filter
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
     * @return array|null
     */
    public function parameters(): ?array
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
        if (!$this->request->filled('search')) {
            return $builder;
        }

        $searchTerm = $this->request->get('search');

        return $builder->where(function (Builder $query) use ($searchTerm) {
            $query->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('username', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Input::make('search')
                ->type('text')
                ->value($this->request->get('search'))
                ->placeholder('Search by name, username or email...')
                ->title($this->name()),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . $this->request->get('search');
    }
}
