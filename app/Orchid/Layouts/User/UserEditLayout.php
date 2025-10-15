<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\Role;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class UserEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->query->get('user');
        $exists = $user && $user->exists;

        // Build employeetype options
        // Include all self-assignable roles PLUS the current user's employeetype (if it exists)
        $employeetypeOptions = $this->buildEmployeetypeOptions($user);

        return [
            Input::make('user.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Name')
                ->placeholder('Name'),

            Input::make('user.email')
                ->type('email')
                ->required()
                ->title('Email')
                ->placeholder('Email'),

            Input::make('user.username')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Username')
                ->placeholder('Username')
                ->help($exists
                    ? 'Username cannot be changed after creation'
                    : 'Unique identifier for the user'
                )
                ->disabled($exists),

            Select::make('user.employeetype')
                ->options($employeetypeOptions)
                ->empty('Select Employee Type...', '')
                ->required()
                ->title('Employee Type')
                ->help('Select the employee type/role for this user'),
        ];
    }

    /**
     * Build employeetype dropdown options
     * Includes all self-assignable roles + current user's employeetype if it exists
     */
    private function buildEmployeetypeOptions($user): array
    {
        // Get all self-assignable roles
        $selfAssignableRoles = Role::where('selfassign', true)
            ->orderBy('name')
            ->get()
            ->pluck('name', 'slug')
            ->toArray();

        // If user exists and has an employeetype, ensure it's in the options
        if ($user && $user->exists && !empty($user->employeetype)) {
            $currentEmployeetype = $user->employeetype;
            
            // Check if current employeetype is already in the list
            if (!isset($selfAssignableRoles[$currentEmployeetype])) {
                // Try to find the role in the database
                $currentRole = Role::where('slug', $currentEmployeetype)->first();
                
                if ($currentRole) {
                    // Add current role to options (even if not self-assignable)
                    // This allows editing existing users with non-self-assignable roles
                    $selfAssignableRoles = [$currentEmployeetype => $currentRole->name . ' (current)'] + $selfAssignableRoles;
                } else {
                    // Role doesn't exist in database - add as-is with note
                    $selfAssignableRoles = [$currentEmployeetype => ucfirst($currentEmployeetype) . ' (current)'] + $selfAssignableRoles;
                }
            }
        }

        return $selfAssignableRoles;
    }
}
