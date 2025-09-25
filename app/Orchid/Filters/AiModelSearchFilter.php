<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class AiModelSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Search Models';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['model_search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('model_search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($search) {
            $query->where('model_id', 'LIKE', "%{$search}%")
                ->orWhere('label', 'LIKE', "%{$search}%")
                ->orWhere('system_id', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('model_search')
                ->type('search')
                ->value($this->request->get('model_search'))
                ->placeholder('Search by model ID, label, or system ID...')
                ->title('Search'),
        ];
    }
}
