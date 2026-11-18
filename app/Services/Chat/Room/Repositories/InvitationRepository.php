<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\Repositories;


use App\Models\Invitation;
use App\Models\Room;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends AbstractRepository<Invitation>
 */
class InvitationRepository extends AbstractRepository
{
    /**
     * Finds all pending invitations of the given user.
     * @param User $user The invited user.
     * @return Collection<int, Invitation>
     */
    public function findAllForUser(User $user): Collection
    {
        return $this->getQuery()->where('username', $user->username)->get();
    }

    /**
     * Finds the pending invitation of the given user for the room with the given slug.
     * @param User $user The invited user.
     * @param string $slug The slug of the room.
     * @return Invitation|null
     */
    public function findOneForUserByRoomSlug(User $user, string $slug): Invitation|null
    {
        return $this->getQuery()
            ->where('username', $user->username)
            ->whereRelation('room', 'slug', $slug)
            ->with('room')
            ->first();
    }

    /**
     * Creates an invitation for the given username in the given room, or updates the existing one.
     * @param Room $room The room to invite to.
     * @param string $username The username of the invited user.
     * @param string $role The role the user will get when accepting the invitation.
     * @param string $iv The iv of the encrypted room key.
     * @param string $tag The tag of the encrypted room key.
     * @param string $encryptedRoomKey The room key, encrypted for the invited user.
     * @return Invitation
     */
    public function createOrUpdate(
        Room   $room,
        string $username,
        string $role,
        string $iv,
        string $tag,
        string $encryptedRoomKey
    ): Invitation
    {
        return $this->getQuery()->updateOrCreate(
            [
                'room_id' => $room->id,
                'username' => $username,
            ],
            [
                'role' => $role,
                'iv' => $iv,
                'tag' => $tag,
                'invitation' => $encryptedRoomKey,
            ]
        );
    }

    /**
     * Deletes all pending invitations of the given user for the given room.
     * @param Room $room The room to delete the invitations for.
     * @param User $user The invited user.
     * @return void
     */
    public function deleteAllForUserInRoom(Room $room, User $user): void
    {
        $this->getQuery()
            ->where('room_id', $room->id)
            ->where('username', $user->username)
            ->delete();
    }
}
