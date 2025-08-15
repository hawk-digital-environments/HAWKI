<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\User;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Password;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Button;
use Orchid\Screen\Layouts\Rows;

class UserPasswordLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        /** @var User $user */
        $user = $this->query->get('user');
        $exists = $user->exists;

        $fields = [];

        // Only show password field for new users (Create Screen)
        if (!$exists) {
            $fields[] = Password::make('user.password')
                ->placeholder('Enter the initial password for the user')
                ->title('Password')
                ->help('This will be the initial password for the local user.')
                ->required(true);
        }

        // Always show the reset password checkbox
        $fields[] = CheckBox::make('user.reset_pw')
            ->value(1)
            ->title('Require Password Reset')
            ->placeholder('User must change password on next login')
            ->help($exists 
                ? 'When enabled, the user will be forced to change their password on next login' 
                : 'New local users are automatically required to reset their password on first login'
            )
            ->disabled(!$exists);

        return $fields;
    }
}
