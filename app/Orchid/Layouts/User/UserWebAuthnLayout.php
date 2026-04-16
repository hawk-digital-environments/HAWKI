<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\User;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Layouts\Rows;

class UserWebAuthnLayout extends Rows
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

        return [
            CheckBox::make('user.webauthn_pk')
                ->title('WebAuthn Passkey Enabled')
                ->placeholder('User has WebAuthn passkey configured')
                ->help('Disable this to reset the user\'s WebAuthn passkey. The user will need to set up a new passkey on their next login.')
                ->sendTrueOrFalse(),
        ];
    }
}
