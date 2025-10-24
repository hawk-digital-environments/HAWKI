<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Platform\Models\Role;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AnnouncementTargetingLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        // Get all available roles
        $roles = Role::all()->pluck('name', 'slug');

        return [
            CheckBox::make('announcement.is_global')
                ->title('Global')
                ->placeholder('Show to all users')
                ->sendTrueOrFalse()
                ->help('If enabled, all users will see this announcement. If disabled, select specific roles below.'),

            Select::make('announcement.target_roles')
                ->title('Target Roles')
                ->options($roles)
                ->multiple()
                ->help('Select which roles should see this announcement (only used when Global is disabled)'),
        ];
    }
}
