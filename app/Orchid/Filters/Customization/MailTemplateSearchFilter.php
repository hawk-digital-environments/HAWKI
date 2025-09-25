<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Customization;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class MailTemplateSearchFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Search Mail Templates';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
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
            $query->where('type', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%");
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
                ->value($this->request->get('search'))
                ->placeholder('Search templates, descriptions, subjects...')
                ->title('Search'),
        ];
    }
}
