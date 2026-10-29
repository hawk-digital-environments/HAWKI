<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\Repositories;


use App\Models\Room;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @extends AbstractRepositoryWithContextualScopes<Room>
 */
class RoomRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Creates a new room. The slug is generated automatically from the name.
     * @param string $roomName The name of the room.
     * @return Room
     */
    public function create(string $roomName): Room
    {
        return $this->getQuery()->create(['room_name' => $roomName]);
    }

    /**
     * Finds a room by its slug. With the default scopes, only rooms the current user is a member of are found.
     * @param string $slug The slug of the room.
     * @return Room|null
     */
    public function findOneBySlug(string $slug): Room|null
    {
        return $this->getQuery()->where('slug', $slug)->first();
    }

    /**
     * Same as {@see findOneBySlug()}, but throws if the room does not exist or is not accessible.
     * @param string $slug The slug of the room.
     * @return Room
     * @throws ModelNotFoundException
     */
    public function findOneBySlugOrFail(string $slug): Room
    {
        return $this->getQuery()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Finds all rooms that have an avatar (room_icon) set.
     * @param string|null $idOrSlug Optionally limits the result to a single room, identified by its id or slug.
     * @return Collection<int, Room>
     */
    public function findAllWithAvatar(?string $idOrSlug = null): Collection
    {
        $query = $this->getQuery()
            ->whereNotNull('room_icon')
            ->where('room_icon', '!=', '');

        if ($idOrSlug !== null) {
            $query->where(is_numeric($idOrSlug) ? 'id' : 'slug', $idOrSlug);
        }

        return $query->get();
    }

    /**
     * Checks if the given user is an active member of the given room with one of the given roles.
     * @param Room $room The room to check.
     * @param User $user The user to check.
     * @param string ...$roles The accepted roles, see the ROLE_* constants on {@see \App\Models\Member}.
     * @return bool
     */
    public function isMemberWithRole(Room $room, User $user, string ...$roles): bool
    {
        return $this->getQuery()
            ->whereKey($room->id)
            ->whereHas('members', fn(Builder $query) => $query
                ->where('user_id', $user->id)
                ->whereIn('role', $roles))
            ->exists();
    }
}
