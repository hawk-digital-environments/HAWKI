<?php
declare(strict_types=1);


namespace App\Services\Chat\Attachment;


use App\Models\AiConvMsg;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;

/**
 * Single authority on whether a user may read a chat attachment, shared by every code path that
 * turns a client-supplied attachment identifier into file content (download proxy, AI requests).
 *
 * The identifier itself is never treated as a secret: access requires the persisted Attachment row
 * to link the file to a message the user can see.
 * - PRIVATE: the attachment belongs to a private AI conversation message owned by the user.
 * - GROUP: the attachment belongs to a message of a room the user is a current member of. When a
 *   room is given, the message must also belong to exactly that room.
 * Every other category, and files without an Attachment row, are denied.
 */
readonly class AttachmentAccessGuard
{
    public function __construct(
        private AttachmentRepository $attachmentRepository,
    )
    {
    }

    public function canAccess(User $user, StoredFileIdentifier $identifier, Room|null $room = null): bool
    {
        $attachable = $this->attachmentRepository->findOneByStoredFileIdentifier($identifier)?->attachable;

        return match ($identifier->category) {
            StoredFileCategory::PRIVATE => $attachable instanceof AiConvMsg
                && (int)$attachable->user_id === (int)$user->id,
            StoredFileCategory::GROUP => $attachable instanceof Message
                && $this->canAccessRoomMessage($user, $attachable, $room),
            default => false,
        };
    }

    private function canAccessRoomMessage(User $user, Message $message, Room|null $room): bool
    {
        if ($room !== null && (int)$message->room_id !== (int)$room->id) {
            return false;
        }

        // The room relation is access-scoped, so it resolves to null for rooms the user is not in.
        $messageRoom = $room ?? $message->room;

        return $messageRoom !== null && $messageRoom->isMember((int)$user->id);
    }
}
