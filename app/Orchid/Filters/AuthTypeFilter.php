<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class AuthTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Authentication Type';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['auth_type'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        if ($this->request->get('auth_type') === 'local') {
            return $builder->where('auth_type', 'local');
        }

        if ($this->request->get('auth_type') === 'external') {
            return $builder->where('auth_type', '!=', 'local');
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
            Select::make('auth_type')
                ->options([
                    'local' => 'Local Users',
                    'external' => 'External Users (LDAP/OIDC/Shibboleth)',
                ])
                ->empty('All Authentication Types')
                ->value($this->request->get('auth_type'))
                ->title($this->name()),
        ];
    }
}
