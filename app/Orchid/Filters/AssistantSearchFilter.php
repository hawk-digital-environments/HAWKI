<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class AssistantSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Search Assistants';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['assistant_search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('assistant_search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('key', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('prompt', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('assistant_search')
                ->type('search')
                ->value($this->request->get('assistant_search'))
                ->placeholder('Search by name, key, description, or prompt type...')
                ->title('Search'),
        ];
    }
}