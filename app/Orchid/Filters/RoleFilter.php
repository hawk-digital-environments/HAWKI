<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class RoleFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return __('Roles');
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['role'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        if (!$this->request->filled('role')) {
            return $builder;
        }

        $roleValue = $this->request->get('role');

        // Special case: filter for users with NO roles
        if ($roleValue === 'no-role') {
            return $builder->whereDoesntHave('roles');
        }

        // Filter for users with specific role
        return $builder->whereHas('roles', function (Builder $query) use ($roleValue) {
            $query->where('slug', $roleValue);
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        // Get all roles from database
        $roles = Role::pluck('name', 'slug')->toArray();
        
        // Add "No Role" option at the beginning
        $options = ['no-role' => 'No Role (Regular Users)'] + $roles;

        return [
            Select::make('role')
                ->options($options)
                ->empty('All Users')
                ->value($this->request->get('role'))
                ->title(__('Roles')),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $roleValue = $this->request->get('role');
        
        if ($roleValue === 'no-role') {
            return $this->name() . ': No Role (Regular Users)';
        }
        
        $role = Role::where('slug', $roleValue)->first();
        
        return $role 
            ? $this->name() . ': ' . $role->name 
            : $this->name();
    }
}
