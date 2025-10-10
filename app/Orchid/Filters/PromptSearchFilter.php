<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class PromptSearchFilter extends Filter
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
    public function parameters(): ?array
    {
        return ['prompt_search'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        if ($this->request->filled('prompt_search')) {
            $search = $this->request->get('prompt_search');
            $builder->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Input::make('prompt_search')
                ->type('search')
                ->placeholder('Search prompts by title or content...')
                ->value($this->request->get('prompt_search'))
                ->title($this->name()),
        ];
    }
}