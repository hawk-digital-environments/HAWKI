<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class SelfAssignFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Self-assignable';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['selfassign'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = $this->request->get('selfassign');

        if ($value === '1') {
            return $builder->where('selfassign', true);
        }

        if ($value === '0') {
            return $builder->where('selfassign', false);
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
        return [
            Select::make('selfassign')
                ->options([
                    '1' => 'Self-assignable',
                    '0' => 'Not self-assignable',
                ])
                ->empty('All Roles')
                ->value($this->request->get('selfassign'))
                ->title($this->name()),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $value = $this->request->get('selfassign');

        if ($value === '1') {
            return $this->name().': Self-assignable';
        }

        if ($value === '0') {
            return $this->name().': Not self-assignable';
        }

        return $this->name();
    }
}
