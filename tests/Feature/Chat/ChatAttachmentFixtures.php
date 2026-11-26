<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Member;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Support\Str;

/**
 * Persists private conversations, rooms and the Attachment rows linking files to their messages.
 */
trait ChatAttachmentFixtures
{
    /**
     * @return string The attachment uuid.
     */
    private function createPrivateAttachment(User $owner, string|null $uuid = null): string
    {
        $conversation = AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $owner->id,
            'system_prompt' => null,
        ]);

        $message = AiConvMsg::query()->create([
            'conv_id' => $conversation->id,
            'user_id' => $owner->id,
            'message_role' => 'user',
            'message_id' => '1',
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
            'completion' => true,
        ]);

        $uuid ??= (string)Str::uuid();
        $message->attachments()->create($this->attachmentAttributes($owner, $uuid, StoredFileCategory::PRIVATE));

        return $uuid;
    }

    private function createRoom(User ...$members): Room
    {
        $room = Room::withoutGlobalScopes()->create([
            'room_name' => 'Room',
            'slug' => Str::slug(Str::random(16)),
        ]);

        foreach ($members as $member) {
            $this->addMember($room, $member);
        }

        return $room;
    }

    private function addMember(Room $room, User $user, bool $isRemoved = false): Member
    {
        return Member::withoutGlobalScopes()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => Member::ROLE_EDITOR,
            'isRemoved' => $isRemoved,
        ]);
    }

    /**
     * @return string The attachment uuid.
     */
    private function createGroupAttachment(Room $room, User $author, string|null $uuid = null): string
    {
        $member = Member::withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->where('user_id', $author->id)
            ->firstOrFail();

        $message = Message::query()->create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'message_id' => '1.000',
            'message_role' => 'user',
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
        ]);

        $uuid ??= (string)Str::uuid();
        $message->attachments()->create($this->attachmentAttributes($author, $uuid, StoredFileCategory::GROUP));

        return $uuid;
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentAttributes(User $user, string $uuid, StoredFileCategory $category): array
    {
        return [
            'uuid' => $uuid,
            'name' => 'secret.jpg',
            'category' => $category->value,
            'type' => 'image',
            'mime' => 'image/jpeg',
            'user_id' => $user->id,
        ];
    }

    private function privateId(string $uuid): StoredFileIdentifier
    {
        return StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
    }

    private function groupId(string $uuid): StoredFileIdentifier
    {
        return StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::GROUP, $uuid);
    }
}
