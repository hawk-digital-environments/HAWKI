<?php

namespace App\Models;

use App\Services\Chat\Events\MemberAddedToRoomEvent;
use App\Services\Chat\Events\MemberRemovedFromRoomEvent;
use App\Services\Chat\Events\MemberUpdatedEvent;
use App\Services\Chat\Events\RoomDeletingEvent;
use App\Services\Chat\Events\RoomUpdatedEvent;
use App\Models\Scopes\RoomAccessScope;
use App\Policies\RoomPolicy;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use Exception;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[UsePolicy(RoomPolicy::class)]
class Room extends Model
{
    use HasContextualScopesTrait;

    private array|null $deferredMemberEvents = null;

    protected $fillable = [
        'room_name',
        'room_icon',
        'room_description',
        'system_prompt',
        'slug'
    ];

    protected $dispatchesEvents = [
        // 'created' is a special case for rooms, so we handle it manually
        // in the RoomFunctions trait to ensure members are added first.
        // 'deleting' is also handled manually to ensure we still can resolve
        // the audience for sync logs.
        'updated' => RoomUpdatedEvent::class
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', RoomAccessScope::class);
    }

    protected static function boot()
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

    public function getMessageById($messageId): Message
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
            $member = $this->members()->where('user_id', $userId)->firstOrFail();
            if (!$member->hasRole($role)) {
                $member->updateRole($role);
                $this->triggerOrDeferMemberEvent(fn() => MemberUpdatedEvent::dispatch($member));
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

                $this->triggerOrDeferMemberEvent(fn() => MemberAddedToRoomEvent::dispatch($member));
            } else {
                // create new member for the room
                $member = $this->members()->create([
                    'user_id' => $userId,
                    'role' => $role,
                ]);

                $this->triggerOrDeferMemberEvent(fn() => MemberAddedToRoomEvent::dispatch($member));
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
                $member = $this->members()->where('user_id', $userId)->firstOrFail();
                $member->revokeMembership();
                // Please don't do it like this. Don't torture your model with injecting
                // random repositories. There should be a service, but the service itself is cumbersome.
                // Will be removed in a future refactor.
                /** @var UserKeychainRepository $repo */
                $repo = app(UserKeychainRepository::class);
                $repo->removeRoomKey($member->user, $member->room);

                $this->triggerOrDeferMemberEvent(fn() => MemberRemovedFromRoomEvent::dispatch($member));

                //Check if All the members have left the room.
                if ($this->members()->count() === 1) {
                    $this->deleteRoom();
                } else if ($member->hasRole(Member::ROLE_ADMIN)) {
                    // If the removed member was an admin, check if there are any admins left. If not, handle this as a "delete" action
                    // @todo in the new api it should not be possible to leave a room without at least one admin!
                    $adminsCount = $this->members()->where('role', Member::ROLE_ADMIN)->count();
                    if ($adminsCount === 0) {
                        $this->delete();
                    }
                }
                return true;
            } catch (\Throwable $e) {
                Log::error("Failed to remove member: $e");
                return false;
            }
        }
        return false;
    }


    public function deleteRoom(): bool
    {
        try {
            RoomDeletingEvent::dispatch($this);
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

    /**
     * Runs the given callback in "deferred member event" mode: any member events
     * triggered inside the callback via {@see triggerOrDeferMemberEvent()} are
     * collected instead of being dispatched immediately.
     *
     * Returns a dispatcher callable that, when invoked, fires all collected events
     * in the order they were recorded. Call it after any outer transaction or state
     * change has been committed so listeners receive a consistent state.
     *
     * @param callable $callback Logic that may add or update room members.
     * @return callable(): void Dispatcher that fires the deferred member events.
     */
    public function runWithDeferredMemberEvents(callable $callback): callable
    {
        $this->deferredMemberEvents = [];
        try {
            $callback();
            $deferredEvents = $this->deferredMemberEvents;
        } finally {
            $this->deferredMemberEvents = null;
        }

        return static function () use ($deferredEvents) {
            if (empty($deferredEvents)) { // @phpstan-ignore empty.variable
                return;
            }
            foreach ($deferredEvents as $event) { // @phpstan-ignore deadCode.unreachable
                $event();
            }
        };
    }

    /**
     * Dispatches a member event immediately, or defers it when inside a
     * {@see runWithDeferredMemberEvents()} call.
     *
     * @param callable(): void $trigger Closure that dispatches the event.
     */
    private function triggerOrDeferMemberEvent(callable $trigger): void
    {
        if (is_array($this->deferredMemberEvents)) {
            $this->deferredMemberEvents[] = $trigger;
        } else {
            $trigger();
        }
    }
}
