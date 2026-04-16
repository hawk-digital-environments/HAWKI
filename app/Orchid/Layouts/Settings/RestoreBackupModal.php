<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Fields\Password;
use Orchid\Screen\Layouts\Rows;

class RestoreBackupModal extends Rows
{

    /**
     * Get the fields elements to be displayed.
     *
     * @return iterable
     */
    protected function fields(): iterable
    {
        return [
            Password::make('admin_password')
                ->title('Admin Password')
                ->placeholder('Enter your password')
                ->help('
                    <div class="alert alert-warning mt-3 mb-0">
                        <h6 class="alert-heading mb-2"><strong>⚠️ WARNING</strong></h6>
                        <p class="mb-2"><strong>This will replace your current database with this backup!</strong></p>
                        <ul class="mb-2" style="padding-left: 20px;">
                            <li>The application will enter maintenance mode during restore</li>
                            <li>All current data will be overwritten</li>
                            <li>All users will be logged out (including yourself)</li>
                        </ul>
                        <p class="mb-0"><strong>Please enter your password to confirm this action.</strong></p>
                    </div>
                ')
                ->required(),
        ];
    }
}
