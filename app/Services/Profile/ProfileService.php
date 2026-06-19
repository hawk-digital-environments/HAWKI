<?php

namespace App\Services\Profile;


use App\Models\PasskeyBackup;
use App\Models\User;
use App\Services\Chat\Room\RoomService;
use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Container\ServiceLocatorTrait;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ProfileService
{
    use ServiceLocatorTrait;

    public function update(array $data): bool
    {
        $user = Auth::user();

        if (!empty($data['displayName'])) {
            $user->update(['name' => $data['displayName']]);
        }

        if (!empty($data['bio'])) {
            $user->update(['bio' => $data['bio']]);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function assignAvatar(UploadedFile $image): string
    {
        $user = Auth::user();

        $avatarStorage = app(AvatarStorageService::class);
        $newAvatar = $avatarStorage->store(
            file: FileReference::fromUploadedFile($image),
            category: StoredFileCategory::PROFILE_AVATAR
        );

        if ($newAvatar === null) {
            throw new \RuntimeException('Failed to store image');
        }

        $avatarStorage->delete(StoredFileIdentifier::tryFromUserAvatar($user));

        $user->update(['avatar_id' => $newAvatar->getUuid()]);

        return $newAvatar->getUrl();
    }


    /**
     * @throws Exception
     */
    public function resetProfile(): void
    {
        $user = Auth::user();
        $this->deleteUserData($user);

        $userInfo = [
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'employeetype' => $user->employeetype,
        ];

        Auth::logout();

        Session::put('registration_access', true);
        Session::put('authenticatedUserInfo', json_encode($userInfo));
    }


    /**
     * @throws Exception
     */
    public function deleteUserData(User $user): void
    {

        try {
            $roomService = $this->getService(RoomService::class);
            $rooms = $user->rooms()->get();

            foreach ($rooms as $room) {
                $member = $room->members()->where('user_id', $user->id)->firstOrFail();
                $roomService->removeMember($member, $room);
            }

            $convs = $user->conversations()->get();

            foreach ($convs as $conv) {
                $conv->messages()->delete();
                $conv->delete();
            }

            $invitations = $user->invitations()->get();
            foreach ($invitations as $inv) {
                $inv->delete();
            }

            $this->getService(AppliedFrontendMigrationRepository::class)->dropAllForUser($user);
            $this->getService(FrontendMigrationUserdataRepository::class)->dropAllForUser($user);
            $this->getService(UserKeychainRepository::class)->dropAllForUser($user);

            $backups = PasskeyBackup::where('username', $user->username)->get();

            foreach ($backups as $backup) {
                $backup->delete();
            }

            $tokens = $user->tokens()->get();
            foreach ($tokens as $token) {
                $token->delete();
            }

            $user->revokProfile();
        } catch (Exception $e) {
            throw $e;
        }
    }
}
