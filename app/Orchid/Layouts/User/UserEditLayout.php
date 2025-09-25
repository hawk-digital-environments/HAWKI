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
                ->fromQuery(Role::where('selfassign', true), 'name', 'slug')
                ->empty('Select Employee Type...', '')
                ->required()
                ->title('Employee Type')
                ->help('Select the employee type/role for this user (only self-assignable roles are shown)'),
        ];
    }
}
