<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AssistantVisibilityFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Visibility';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['assistant_visibility'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $visibility = $this->request->get('assistant_visibility');

        if (empty($visibility)) {
            return $builder;
        }

        return $builder->where('visibility', $visibility);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('assistant_visibility')
                ->options([
                    'public' => 'Public',
                    'org' => 'Organization',
                    'private' => 'Private',
                ])
                ->value($this->request->get('assistant_visibility'))
                ->empty('All Visibilities')
                ->title('Visibility'),
        ];
    }
}