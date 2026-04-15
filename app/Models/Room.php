<?php

namespace App\Models;

use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\StoredFileIdentifier;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Room extends Model
{
    protected $fillable = [
        'room_name',
        'room_icon',
        'room_description',
        'system_prompt',
        'slug'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($room) {
            $room->slug = Str::slug($room->room_name) . '-' . Str::random(6);
        });
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('message_id');
    }

    /**
     * @param string $messageId
     * @return Message
     */
    public function getMessageById(string $messageId): Message
    {
        return $this->messages()->where('message_id', $messageId)->firstOrFail();
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function membersAll(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }

    /**
     * @param int $userId
     * @return bool
     */
    public function isMember(int $userId): bool
    {
        return $this->members()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function oldMembers(): HasMany
    {
        return $this->hasMany(Member::class)->where('isRemoved', true);
    }

    /**
     * @param int $userId
     * @return bool
     */
    public function isOldMember(int $userId): bool
    {
        return $this->oldMembers()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @param int $userId
     * @param string $role
     */
    public function addMember(int $userId, string $role): void
    {
        if ($this->isMember($userId)) {
            $member = $this->members()->where('user_id', $userId)->first();
            if (!$member->hasRole($role)) {
                $member->updateRole($role);
            }
        } else {
            if ($this->isOldMember($userId)) {

                // if an old membership exists for the user
                // reactivate the old membership.
                $member = $this->membersAll()->where('user_id', $userId)->first();
                $member->recreateMembership();

                if (!$member->hasRole($role)) {
                    $member->updateRole($role);
                }

            } else {
                // create new member for the room
                $this->members()->create([
                    'user_id' => $userId,
                    'role' => $role,
                ]);
            }

        }
    }

    /**
     * @param int $userId
     * @return bool
     */
    public function removeMember(int $userId): bool
    {
        if ($this->isMember($userId)) {
            try {
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
            } catch (Exception $e) {
                Log::error("Failed to remove member: $e");
                return false;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function deleteRoom(): bool
    {
        try {
            // Delete related messages and members
            $messages = $this->messages()->get();
            foreach ($messages as $message) {
                $messageHandler = app(GroupMessageHandler::class);
                $messageHandler->delete($this, $message->toArray());
            }
            $this->members()->delete();
            if ($this->room_icon) {
                $avatarStorage = app(AvatarStorageService::class);
                $avatarStorage->delete(StoredFileIdentifier::tryFromRoomAvatar($this));
            }
            // Delete the room itself
            $this->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove member: $e");
            return false;
        }
    }
}
