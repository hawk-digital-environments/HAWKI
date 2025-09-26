<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\AiAssistantPrompt;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class PromptCategoryFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Category';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['prompt_category'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        if ($this->request->filled('prompt_category')) {
            $builder->where('category', $this->request->get('prompt_category'));
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        $categories = AiAssistantPrompt::distinct('category')
            ->whereNotNull('category')
            ->pluck('category', 'category')
            ->toArray();

        return [
            Select::make('prompt_category')
                ->empty('All Categories')
                ->options($categories)
                ->value($this->request->get('prompt_category'))
                ->title($this->name()),
        ];
    }
}