<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class PromptCreatorFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Creator';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['prompt_creator'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        if ($this->request->filled('prompt_creator')) {
            $builder->where('created_by', $this->request->get('prompt_creator'));
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        $creators = User::whereHas('createdPrompts')
            ->select('id', 'name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            Select::make('prompt_creator')
                ->empty('All Creators')
                ->options($creators)
                ->value($this->request->get('prompt_creator'))
                ->title($this->name()),
        ];
    }
}