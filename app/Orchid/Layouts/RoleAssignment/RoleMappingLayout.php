<?php

namespace App\Orchid\Layouts\RoleAssignment;

use Orchid\Platform\Models\Role;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class RoleMappingLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return \Orchid\Screen\Field[]
     */
    protected function fields(): iterable
    {
        return [
            Select::make('assigned_roles')
                ->fromQuery(Role::query(), 'name', 'id')
                ->multiple()
                ->title('Assigned Roles')
                ->help('Select one or more roles that users with this employeetype will receive'),
        ];
    }
}
