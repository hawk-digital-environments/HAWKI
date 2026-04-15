<?php

namespace App\Services\Profile;


use App\Models\PasskeyBackup;
use App\Models\PrivateUserData;
use App\Models\User;
use App\Services\Chat\Room\RoomService;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\FileReference;
use App\Services\Storage\Value\StoredFileCategory;
use App\Services\Storage\Value\StoredFileIdentifier;
use App\Utils\ServiceLocatorTrait;
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
            $roomService = $this->getServiceInstance(RoomService::class);
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

            $prvUserData = PrivateUserData::where('user_id', $user->id)->get();
            foreach ($prvUserData as $data) {
                $data->delete();
            }

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


    /// Sends back user's encrypted keychain
    public function fetchUserKeychain(): string
    {

        $user = Auth::user();
        $prvUserData = PrivateUserData::where('user_id', $user->id)->first();

        // Corrupted user data, force re-registration
        if ($prvUserData === null) {
            $this->deleteUserData($user);
            redirect()->route('login')
                ->withErrors(['login_error' => 'User data corrupted. Please register again.'])->send();
            exit();
        }

        return json_encode([
            'keychain' => $prvUserData->keychain,
            'KCIV' => $prvUserData->KCIV,
            'KCTAG' => $prvUserData->KCTAG,
        ]);
    }

}
