<?php

namespace App\Models;

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
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }
    public function isMember($userId): bool
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->exists();
    }

    public function oldMembers(): HasMany
    {
        return $this->hasMany(Member::class)->where('isRemoved', true);
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
                // reactivate the old membership.
                $member = $this->membersAll()->where('user_id', $userId)->first();
                $member->recreateMembership();

                if(!$member->hasRole($role)){
                    $member->updateRole($role);
                }

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


    public function deleteRoom(): bool{
        try{
            // Delete related messages and members
            $this->messages()->delete();
            $this->members()->delete();
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
            if(!$msgs[$i]->isReadBy($member)){
                return true;
            }
        }
        return false;
    }
}
