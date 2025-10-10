<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AssistantStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Status';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['assistant_status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('assistant_status');

        if (empty($status)) {
            return $builder;
        }

        return $builder->where('status', $status);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('assistant_status')
                ->options([
                    'active' => 'Active',
                    'draft' => 'Draft',
                    'archived' => 'Archived',
                ])
                ->value($this->request->get('assistant_status'))
                ->empty('All Statuses')
                ->title('Status'),
        ];
    }
}