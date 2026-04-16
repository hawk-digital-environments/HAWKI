<?php

namespace App\Services\Chat\Room\Traits;

use App\Models\Room;
use App\Models\Member;

use App\Services\Storage\AvatarStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;

trait RoomFunctions
{

    public function create(array $data): Room
    {
        // Create the room with name and description
        $room = Room::create([
            'room_name' => $data['room_name'],
        ]);
        // Add AI as assistant
        $room->addMember(1, Member::ROLE_ASSISTANT);
        // Add the creator as admin
        $room->addMember(Auth::id(), Member::ROLE_ADMIN);

        return $room;
    }

    public function load($slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if (!$room->isMember(Auth::id())) {
            throw new AuthorizationException();
        }
        $roomIcon = $room->room_icon ?
                    $this->avatarStorage->getUrl($room->room_icon,'room_avatars') :
                    '';

        $membership = $room->members()->where('user_id', Auth::id())->first();
        $membership->updateLastRead();

        $role = $membership->role;
        
        // Viewers can only see Admin + themselves
        $canViewAllMembers = $membership->canViewAllMembers();
        
        $membersData = $canViewAllMembers 
            ? $room->members->map(function ($member) {
                return [
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->role,
                    'employeetype' => $member->user->employeetype,
                    'avatar_url' => !empty($member->user->avatar_id) ?
                                    $this->avatarStorage->getUrl($member->user->avatar_id, 'profile_avatars')
                                    : null
                ];
            })->values()
            : $room->members->filter(function ($member) {
                // Show admins, assistants (AI), and current user to Viewers
                return $member->role === \App\Models\Member::ROLE_ADMIN || 
                       $member->role === \App\Models\Member::ROLE_ASSISTANT ||
                       $member->user_id === Auth::id();
            })->map(function ($member) {
                return [
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->role,
                    'employeetype' => $member->user->employeetype,
                    'avatar_url' => !empty($member->user->avatar_id) ?
                                    $this->avatarStorage->getUrl($member->user->avatar_id, 'profile_avatars')
                                    : null
                ];
            })->values();

        $data = [
            'id' => $room->id,
            'name' => $room->room_name,
            'room_icon' => $roomIcon,
            'slug' => $room->slug,
            'system_prompt' => $room->system_prompt,
            'room_description' => $room->room_description,
            'role' => $role,
            'total_members_count' => $room->members()
                ->whereHas('user', function($query) {
                    $query->where('employeetype', '!=', 'system')
                          ->where('employeetype', '!=', 'AI');
                })
                ->count(),
            'can_view_all_members' => $canViewAllMembers,

            'members' => $membersData,

            'invitations' => $room->invitations->map(function ($invitation) {
                $user = \App\Models\User::where('username', $invitation->username)->first();
                return [
                    'username' => $invitation->username,
                    'name' => $user ? $user->name : $invitation->username,
                    'role' => $invitation->role,
                    'avatar_url' => ($user && !empty($user->avatar_id)) ?
                                    $this->avatarStorage->getUrl($user->avatar_id, 'profile_avatars')
                                    : null,
                    'isPending' => true
                ];
            }),

            'messagesData' => $room->messageObjects()
        ];

        return $data;
    }

    public function update(array $data, string $slug)
    {

        $room = Room::where('slug', $slug)->firstOrFail();

        try {
            if(!empty($data['image'])){
                $this->assignAvatar($data['image'], $slug);
            }
            if (!empty($data['system_prompt'])) {
                $room->update(['system_prompt' => $data['system_prompt']]);
            }
            if (!empty($data['description'])) {
                $room->update(['room_description' => $data['description']]);
            }
            if (!empty($data['name'])) {
                $room->update(['room_name' => $data['name']]);
            }
            return true;
        } catch (Exception $e) {
            Log::error("Failed to update Room Information. Error: $e");
            return false;
        }
    }


    public function assignAvatar($image, string $slug): array{
        $uuid = Str::uuid();
        $extension = $image->getClientOriginalExtension();
        if (!$extension) {
            $mime = $image->getMimeType();
            $extension = \Illuminate\Support\Arr::last(explode('/', $mime));
        }
        $filename = $uuid . '.' . $extension;

        $avatarStorage = app(AvatarStorageService::class);
        try{
            $avatarStorage->store($image,
                $filename,
                $uuid,
                'room_avatars');

            $room = Room::where('slug', $slug)->firstOrFail();

            if($room->room_icon){
                $avatarStorage->delete($room->room_icon,'room_avatars');
            }

            $room->update(['room_icon' => $uuid]);

            return [
                'url'=> $avatarStorage->getUrl($uuid, 'room_avatars'),
                'uuid' => $uuid,
            ];
        } catch(Exception $e) {
            throw new Exception('Failed to store image: ' . $e->getMessage());
        }
    }

    public function removeAvatar(string $slug): void
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if (!$room->isMember(Auth::id())) {
            throw new AuthorizationException();
        }

        if ($room->room_icon) {
            $avatarStorage = app(AvatarStorageService::class);
            try {
                $avatarStorage->delete($room->room_icon, 'room_avatars');
            } catch (Exception $e) {
                // Log error but continue with database update
                \Log::error('Failed to delete room avatar file: ' . $e->getMessage());
            }

            $room->update(['room_icon' => null]);
        }
    }


    public function delete($slug){
        $room = Room::where('slug', $slug)->firstOrFail();

        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }

        try{
            $room->deleteRoom();
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to remove Room Information. Error: $e");
            return false;
        }
    }

}
