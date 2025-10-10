<?php

declare(strict_types=1);

namespace App\Orchid\Filters\ModelSettings;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;

class PromptFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Search Prompts';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['prompt_search', 'prompt_status', 'prompt_category'];
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
        if ($this->request->filled('prompt_search')) {
            $search = $this->request->get('prompt_search');
            $builder->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($this->request->filled('prompt_status')) {
            $builder->where('status', $this->request->get('prompt_status'));
        }

        if ($this->request->filled('prompt_category')) {
            $builder->where('category', $this->request->get('prompt_category'));
        }

        return $builder;
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [];
    }
}