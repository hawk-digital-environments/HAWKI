<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class UsageRecordTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Type';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['type'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $type = $this->request->get('type');

        if (empty($type)) {
            return $builder;
        }

        return $builder->where('type', $type);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('type')
                ->options([
                    'private' => 'Private',
                    'group' => 'Group',
                    'api' => 'API',
                    'title' => 'Title Generator',
                    'improver' => 'Prompt Improver',
                    'summarizer' => 'Summarizer',
                ])
                ->empty('All Types')
                ->value($this->request->get('type'))
                ->title($this->name()),
        ];
    }
}
