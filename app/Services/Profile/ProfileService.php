<?php

namespace App\Services\Profile;


use App\Events\PersonalAccessTokenRemovedEvent;
use App\Events\UserRemovedEvent;
use App\Events\UserUpdatedEvent;
use App\Models\PasskeyBackup;
use App\Models\PrivateUserData;
use App\Models\User;
use App\Services\Chat\Room\RoomService;
use App\Services\Profile\Traits\ApiTokenHandler;
use App\Services\Profile\Traits\PasskeyHandler;
use App\Services\Storage\AvatarStorageService;
use App\Services\User\Keychain\UserKeychainDb;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ProfileService{


    public function update(array $data): bool{
        $user = Auth::user();

        if(!empty($data['displayName'])){
            $user->update(['name' => $data['displayName']]);
        }

        if(!empty($data['bio'])){
            $user->update(['bio' => $data['bio']]);
        }

        UserUpdatedEvent::dispatch($user);

        return true;
    }

    /**
     * @throws Exception
     */
    public function assignAvatar($image): string{
        $uuid = Str::uuid();
        $user = Auth::user();
        $extension = $image->getClientOriginalExtension(); // returns 'png', 'jpg', etc.
        if (!$extension) {
            // Fall back on MIME type if extension missing
            $mime = $image->getMimeType(); // e.g. 'image/png'
            $extension = \Illuminate\Support\Arr::last(explode('/', $mime));
        }
        $filename = $uuid . '.' . $extension;

        $avatarStorage = app(AvatarStorageService::class);
        $response = $avatarStorage->store($image,
                                        $filename,
                                        $uuid,
                                        'profile_avatars',
                                        false);
        if ($response) {
            if($user->avatar_id != null){
                $avatarStorage->delete($user->avatar_id, 'profile_avatars');
            }

            $user->update(['avatar_id' => $uuid]);
            UserUpdatedEvent::dispatch($user);
            return $avatarStorage->getUrl($uuid, 'profile_avatars');
        } else {
            throw new Exception('Failed to store image');
        }
    }


    /**
     * @throws Exception
     */
    public function resetProfile(): void{
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
    public function deleteUserData(User $user): void{

        try{
            UserRemovedEvent::dispatch($user);

            $roomService = new RoomService();
            $rooms = $user->rooms()->get();

            foreach($rooms as $room){
                $member = $room->members()->where('user_id', $user->id)->firstOrFail();
                if ($member) {
                    $response = $roomService->removeMember($member, $room);
                }
            }

            $convs = $user->conversations()->get();

            foreach($convs as $conv){
                $conv->messages()->delete();
                $conv->delete();
            }

            $invitations = $user->invitations()->get();
            foreach($invitations as $inv){
                $inv->delete();
            }

            $prvUserData = PrivateUserData::where('user_id', $user->id)->get();
            foreach($prvUserData as $data){
                $data->delete();
            }

            app(UserKeychainDb::class)->removeAllOfUser($user);

            $backups = PasskeyBackup::where('username', $user->username)->get();

            foreach($backups as $backup){
                $backup->delete();
            }

            $tokens = $user->tokens()->get();
            foreach($tokens as $token){
                PersonalAccessTokenRemovedEvent::dispatch($user, $token);
                $token->delete();
            }

            $user->revokProfile();
        }
        catch(Exception $e){
            throw $e;
        }
    }

}
