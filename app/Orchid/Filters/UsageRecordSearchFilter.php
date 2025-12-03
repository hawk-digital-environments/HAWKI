<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class UsageRecordSearchFilter extends Filter
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
            $query->where('id', 'like', '%'.$search.'%')
                ->orWhere('type', 'like', '%'.$search.'%')
                ->orWhere('model', 'like', '%'.$search.'%')
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
                ->orWhereHas('room', function ($roomQuery) use ($search) {
                    $roomQuery->where('room_name', 'like', '%'.$search.'%');
                });
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
                ->placeholder('Search by ID, Type, Model, User or Room...')
                ->title($this->name()),
        ];
    }
}
