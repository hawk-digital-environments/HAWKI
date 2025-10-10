<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Layouts\Rows;

class UserApprovalLayout extends Rows
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
            CheckBox::make('user.approval')
                ->title('User Approval Status')
                ->placeholder('User is approved for system access')
                ->help($exists
                    ? 'Check to approve this user for system access. Unchecked users cannot access the system.'
                    : 'Set initial approval status for the new user'
                )
                ->value($exists ? $user->approval : true)
                ->sendTrueOrFalse(),
        ];
    }
}
