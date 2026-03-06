<?php

namespace App\Models;

use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Storage\AvatarStorageService;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_name',
        'room_icon',
        'room_description',
        'system_prompt',
        'slug'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($room) {
            $room->slug = Str::slug($room->room_name) . '-' . Str::random(6);
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('message_id');
    }

    public function getMessageById($messageId): Message
    {
        return $this->messages()->where('message_id', $messageId)->firstOrFail();
    }

    public function messageObjects(): array
    {
        $messages = $this->messages;

        $messagesData = array();
        foreach ($messages as $message){
            $msgData = $message->createMessageObject();
            array_push($messagesData, $msgData);
        }
        return $messagesData;
    }


    public function membersAll(): HasMany
    {
        return $this->hasMany(Member::class);
    }
    public function members(): HasMany
    {
        return $this->hasMany(Member::class)
            ->where('isMember', true);
    }
    
    public function isMember($userId): bool
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->exists();
    }

    public function oldMembers(): HasMany
    {
        return $this->hasMany(Member::class)
            ->where('isMember', false);
    }
    
    public function isOldMember($userId): bool
    {
        return $this->oldMembers()
                    ->where('user_id', $userId)
                    ->exists();
    }

    public function addMember($userId, $role): void
    {
        if($this->isMember($userId)){
            $member = $this->members()->where('user_id', $userId)->first();
            if(!$member->hasRole($role)){
                $member->updateRole($role);
            }
        }
        else{
            if($this->isOldMember($userId)){
                // if an old membership exists for the user
                // reactivate the old membership with new role
                $member = $this->membersAll()->where('user_id', $userId)->first();
                $member->recreateMembership($role);
            }
            else{
                // create new member for the room
                $this->members()->create([
                    'user_id' => $userId,
                    'role' => $role,
                ]);
            }
        }
    }

    public function removeMember($userId): bool
    {
        if($this->isMember($userId)){
            try{
                // Attempt to delete the member from the room based on user ID
                $this->members()
                    ->where('user_id', $userId)
                    ->firstOrFail()
                    ->revokeMembership();

                //Check if All the members have left the room.
                if ($this->members()->count() === 1) {
                    $this->deleteRoom();
                }
                return true;
            }
            catch(Exception $e){
                Log::error("Failed to remove member: $e");
                return false;
            }
        }
        return false;
    }
    
    // Permission helper methods
    public function getMemberByUserId($userId): ?Member
    {
        return $this->members()->where('user_id', $userId)->first();
    }
    
    public function userCanSendMessages($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canSendMessages();
    }
    
    public function userCanModifyRoom($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canModifyRoom();
    }
    
    public function userCanAddMembers($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canAddMembers();
    }
    
    public function userCanRemoveMembers($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canRemoveMembers();
    }
    
    public function userCanDeleteRoom($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canDeleteRoom();
    }
    
    public function userCanViewAllMembers($userId): bool
    {
        $member = $this->getMemberByUserId($userId);
        return $member && $member->canViewAllMembers();
    }


    public function deleteRoom(): bool{
        try{
            // Delete related messages and members
            $messages = $this->messages()->get();
            foreach ($messages as $message){
                $messageHandler = MessageHandlerFactory::create('group');
                $messageHandler->delete($this, $message->toArray());
            }
            $this->members()->delete();
            if($this->room_icon){
                $avatarStorage = app(AvatarStorageService::class);
                $avatarStorage->delete($this->room_icon,'room_avatars');
            }
            // Delete the room itself
            $this->delete();
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to remove member: $e");
            return false;
        }
    }



    public function hasRole($userId, $role): bool
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->where('role', $role)
                    ->exists();
    }


    public function changeName($newName): bool
    {
        $this->update(['room_name' => $newName]);
    }



    public function hasUnreadMessagesFor($member): bool
    {
        // get the last 100 messages.
        $msgs = $this->messages()
        ->orderBy('updated_at', 'desc')
        ->take(100)
        ->get();

        // iterate the messages in reverse order from the newest to the oldest.
        for ($i = count($msgs) - 1; $i >= 0; $i--) {
            $msg = $msgs[$i];
            
            // Skip own messages - they're automatically "read"
            if ($msg->member_id === $member->id) {
                continue;
            }
            
            // Check if message is read by this member
            if(!$msg->isReadBy($member)){
                return true;
            }
        }
        return false;
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
