<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\Role;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class UserRoleLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        $user = $this->query->get('user');
        $isApproved = $user ? $user->approval : true; // Default to true for new users
        
        return [
            Select::make('user.roles.')
                ->fromModel(Role::class, 'name')
                ->multiple()
                ->title('Orchid Platform Roles')
                ->help($isApproved 
                    ? 'Select which Orchid platform roles this user should have. The role corresponding to the employeetype is automatically added but additional roles can be assigned here.'
                    : 'Role assignment is disabled because this user is not approved. Enable user approval first to assign roles.')
                ->disabled(!$isApproved),
        ];
    }
}
