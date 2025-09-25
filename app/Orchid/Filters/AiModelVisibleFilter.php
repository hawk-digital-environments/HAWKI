<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AiModelVisibleFilter extends Filter
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
        return ['visible_status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('visible_status');

        if (is_null($status) || $status === '') {
            return $builder;
        }

        return $builder->where('ai_models.is_visible', (bool) $status);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('visible_status')
                ->options([
                    '' => 'All Models',
                    '1' => 'Visible',
                    '0' => 'Hidden',
                ])
                ->value($this->request->get('visible_status'))
                ->title('Visibility')
                ->empty('All Models'),
        ];
    }

    /**
     * Get the display value for the filter.
     */
    public function value(): string
    {
        $status = $this->request->get('visible_status');
        
        return match ($status) {
            '1' => 'Visible',
            '0' => 'Hidden',
            default => 'All Models'
        };
    }
}
