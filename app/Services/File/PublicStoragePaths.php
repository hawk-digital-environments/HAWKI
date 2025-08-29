<?php
declare(strict_types=1);


namespace App\Services\File;


use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;

readonly class PublicStoragePaths
{
    private Filesystem $publicStorage;

    public function __construct(FilesystemManager $filesystemManager)
    {
        $this->publicStorage = $filesystemManager->disk('public');
    }

    public function getRoomAvatarPath(Room $room): string|null
    {
        if (empty($room->room_icon)) {
            return null;
        }

        return $this->publicStorage->url('room_avatars/' . $room->room_icon);
    }

    public function getUserProfileAvatarPath(User $user): string|null
    {
        if (empty($user->avatar_id)) {
            return null;
        }

        return $this->publicStorage->url('profile_avatars/' . $user->avatar_id);
    }
}
