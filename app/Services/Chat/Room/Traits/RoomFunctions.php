<?php

namespace App\Services\Chat\Room\Traits;

use App\Models\Room;
use App\Models\Member;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;

trait RoomFunctions{

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

        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }

        $roomIcon = $this->avatarStorage->getFileUrl('room_avatars', $room->slug, $room->room_icon);
        $membership = $room->members()->where('user_id', Auth::id())->first();
        $membership->updateLastRead();

        $role = $membership->role;

        $data = [
            'id' => $room->id,
            'name' => $room->room_name,
            'room_icon' => $roomIcon,
            'slug' => $room->slug,
            'system_prompt' => $room->system_prompt,
            'room_description' => $room->room_description,
            'role' => $role,

            'members' => $room->members->map(function ($member) {
                return [
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->role,
                    'employeetype' => $member->user->employeetype,
                    'avatar_url' => $this->avatarStorage->getFileUrl('profile_avatars',
                                                                    $member->user->username,
                                                                    $member->user->avatar_id)
                ];
            }),

            'messagesData' => $room->messageObjects()
        ];

        return $data;
    }

    public function update(array $data, string $slug){

        $room = Room::where('slug', $slug)->firstOrFail();

        try{
            if(!empty($data['img'])){
                $this->avatarStorage->storeFile($data['img'], 'room_avatars', $slug, Str::uuid());
            }
            if(!empty($data['system_prompt'])){
                $room->update(['system_prompt' => $data['system_prompt']]);
            }
            if(!empty($data['description'])){
                $room->update(['room_description' => $data['description']]);
            }
            if(!empty($data['name'])){
                $room->update(['room_name' => $data['name']]);
            }
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to update Room Information. Error: $e");
            return false;
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
