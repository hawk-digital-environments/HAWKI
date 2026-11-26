<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\Message;
use App\Models\Attachment;
use App\Models\User;
use App\Services\Chat\Attachment\AttachmentAccessGuard;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AttachmentAccessGuard::class)]
class AttachmentAccessGuardTest extends TestCase
{
    use DatabaseTransactions;
    use ChatAttachmentFixtures;

    // =========================================================================
    // Private attachments
    // =========================================================================

    public function testOwnerCanAccessPrivateAttachment(): void
    {
        $owner = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->actingAs($owner);

        self::assertTrue($this->guard()->canAccess($owner, $this->privateId($uuid)));
    }

    public function testOtherUserCannotAccessPrivateAttachment(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->actingAs($attacker);

        self::assertFalse($this->guard()->canAccess($attacker, $this->privateId($uuid)));
    }

    public function testPrivateAttachmentCannotBeReadThroughGroupCategory(): void
    {
        $owner = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->actingAs($owner);

        self::assertFalse($this->guard()->canAccess($owner, $this->groupId($uuid)));
    }

    public function testUnknownUuidIsDenied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        self::assertFalse($this->guard()->canAccess($user, $this->privateId((string)Str::uuid())));
        self::assertFalse($this->guard()->canAccess($user, $this->groupId((string)Str::uuid())));
    }

    public function testPrivateAttachmentNotLinkedToAPrivateMessageIsDenied(): void
    {
        $user = User::factory()->create();
        $uuid = (string)Str::uuid();
        $attachment = new Attachment($this->attachmentAttributes($user, $uuid, StoredFileCategory::PRIVATE));
        $attachment->attachable_type = Message::class;
        $attachment->attachable_id = \PHP_INT_MAX;
        $attachment->save();

        $this->actingAs($user);

        self::assertFalse($this->guard()->canAccess($user, $this->privateId($uuid)));
    }

    public function testAvatarCategoriesAreNeverGrantedAsChatAttachments(): void
    {
        $owner = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->actingAs($owner);

        self::assertFalse($this->guard()->canAccess(
            $owner,
            StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PROFILE_AVATAR, $uuid)
        ));
    }

    // =========================================================================
    // Group attachments
    // =========================================================================

    public function testRoomMemberCanAccessGroupAttachment(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $room = $this->createRoom($author, $member);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($member);

        self::assertTrue($this->guard()->canAccess($member, $this->groupId($uuid)));
        self::assertTrue($this->guard()->canAccess($member, $this->groupId($uuid), $room));
    }

    public function testNonMemberCannotAccessGroupAttachment(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $room = $this->createRoom($author);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($outsider);

        self::assertFalse($this->guard()->canAccess($outsider, $this->groupId($uuid)));
    }

    public function testRemovedMemberCannotAccessGroupAttachment(): void
    {
        $author = User::factory()->create();
        $formerMember = User::factory()->create();
        $room = $this->createRoom($author);
        $this->addMember($room, $formerMember, isRemoved: true);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($formerMember);

        self::assertFalse($this->guard()->canAccess($formerMember, $this->groupId($uuid)));
    }

    public function testGroupAttachmentIsDeniedWhenRequestedForAnotherRoom(): void
    {
        $user = User::factory()->create();
        $roomA = $this->createRoom($user);
        $roomB = $this->createRoom($user);
        $uuidFromB = $this->createGroupAttachment($roomB, $user);

        $this->actingAs($user);

        // Member of both rooms, yet a room-A request must not pull in room B's files.
        self::assertFalse($this->guard()->canAccess($user, $this->groupId($uuidFromB), $roomA));
        self::assertTrue($this->guard()->canAccess($user, $this->groupId($uuidFromB), $roomB));
    }

    public function testGroupAttachmentCannotBeReadThroughPrivateCategory(): void
    {
        $author = User::factory()->create();
        $room = $this->createRoom($author);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($author);

        self::assertFalse($this->guard()->canAccess($author, $this->privateId($uuid)));
    }

    // =========================================================================

    private function guard(): AttachmentAccessGuard
    {
        return $this->app->make(AttachmentAccessGuard::class);
    }
}
