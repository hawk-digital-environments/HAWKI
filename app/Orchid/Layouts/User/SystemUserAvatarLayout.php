<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\ViewField;
use Orchid\Screen\Layouts\Rows;

class SystemUserAvatarLayout extends Rows
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

        // Use UserPresenter to get avatar URL (same logic as everywhere else)
        $presenter = new \App\Orchid\Presenters\UserPresenter($user);
        $currentAvatarUrl = $presenter->image();

        $fields = [];

        // Show current avatar using custom Blade view
        if ($currentAvatarUrl) {
            $fields[] = ViewField::make('avatar_preview')
                ->view('orchid.fields.avatar-preview')
                ->set('avatarUrl', $currentAvatarUrl)
                ->title('Current Avatar');
        }

        // Simple file upload input
        $fields[] = Input::make('user.avatar_file')
            ->type('file')
            ->accept('image/*')
            ->title($currentAvatarUrl ? 'Upload New Avatar' : 'Upload Avatar')
            ->help('Upload a new profile picture (max 10MB). Supported formats: JPG, PNG, GIF, WebP.');

        return $fields;
    }
}
