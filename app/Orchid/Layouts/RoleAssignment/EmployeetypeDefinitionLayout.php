<?php

namespace App\Orchid\Layouts\RoleAssignment;

use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class EmployeetypeDefinitionLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return \Orchid\Screen\Field[]
     */
    protected function fields(): iterable
    {
        return [
            Group::make([
                Select::make('employeetype.auth_method')
                    ->title('Authentication Method')
                    ->options([
                        'LDAP' => 'LDAP',
                        'OIDC' => 'OIDC', 
                        'Shibboleth' => 'Shibboleth',
                        'system' => 'System',
                    ])
                    ->help('Which authentication system provides this employeetype value')
                    ->required(),

                Input::make('employeetype.raw_value')
                    ->title('Raw Value')
                    ->placeholder('e.g., "42", "admin", "1"')
                    ->help('Exact value that the authentication system returns')
                    ->required(),
            ]),

            Group::make([
                Input::make('employeetype.display_name')
                    ->title('Display Name')
                    ->placeholder('e.g., "Administrator", "Student", "Guest"')
                    ->help('Human-readable name for this employeetype value')
                    ->required(),

                Switcher::make('employeetype.is_active')
                    ->title('Active')
                    ->help('Whether this mapping is currently active')
                    ->sendTrueOrFalse(),
            ]),

            TextArea::make('employeetype.description')
                ->title('Description')
                ->placeholder('Optional description explaining what this employeetype represents')
                ->help('Additional context about when and how this mapping is used')
                ->rows(3),
        ];
    }
}
