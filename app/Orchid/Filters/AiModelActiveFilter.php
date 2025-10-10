<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AiModelActiveFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Active Status';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['active_status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('active_status');

        if (is_null($status) || $status === '') {
            return $builder;
        }

        return $builder->where('ai_models.is_active', (bool) $status);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('active_status')
                ->options([
                    '' => 'All Models',
                    '1' => 'Active',
                    '0' => 'Inactive',
                ])
                ->value($this->request->get('active_status'))
                ->title('Status')
                ->empty('All Models'),
        ];
    }

    /**
     * Get the display value for the filter.
     */
    public function value(): string
    {
        $status = $this->request->get('active_status');
        
        return match ($status) {
            '1' => 'Active',
            '0' => 'Inactive',
            default => 'All Models'
        };
    }
}
