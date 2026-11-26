<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\AiConv;
use App\Models\Attachment;
use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use App\Services\Chat\Attachment\AttachmentAccessGuard;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * A UUID that is already linked to a message must never be linked to a second one: that copy would
 * grant its new audience access to the file and, once deleted, remove the original file as well.
 */
#[CoversClass(PrivateMessageHandler::class)]
#[CoversClass(GroupMessageHandler::class)]
class MessageHandlerAttachmentRelinkTest extends TestCase
{
    use DatabaseTransactions;
    use ChatAttachmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Config::string('filesystems.file_storage'));
        Event::fake();
    }

    public function testForeignPrivateUuidIsNotLinkedToAnotherUsersMessage(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $uuid = $this->createPrivateAttachment($victim, $this->storeFile(StoredFileCategory::PRIVATE));

        $this->actingAs($attacker);
        $message = $this->app->make(PrivateMessageHandler::class)
            ->create($this->createConversation($attacker), $this->privateMessageData($uuid), $attacker);

        self::assertSame(0, $message->attachments()->count());
        self::assertSame(1, Attachment::query()->where('uuid', $uuid)->count());
        self::assertFalse($this->app->make(AttachmentAccessGuard::class)->canAccess($attacker, $this->privateId($uuid)));
    }

    public function testGroupUuidIsNotCopiedIntoAnotherRoom(): void
    {
        $author = User::factory()->create();
        $copier = User::factory()->create();
        $sourceRoom = $this->createRoom($author, $copier);
        $targetRoom = $this->createRoom($copier);
        $uuid = $this->createGroupAttachment($sourceRoom, $author, $this->storeFile(StoredFileCategory::GROUP));

        $this->actingAs($copier);
        $message = $this->app->make(GroupMessageHandler::class)
            ->create($targetRoom, $this->groupMessageData($this->memberOf($targetRoom, $copier), $uuid), $copier);

        self::assertSame(0, $message->attachments()->count());
        self::assertSame(1, Attachment::query()->where('uuid', $uuid)->count());
    }

    public function testFreshUploadIsStillLinked(): void
    {
        $user = User::factory()->create();
        $upload = $this->app->make(FileStorageService::class)
            ->storeTemporary(FileReference::fromContent('note.txt', 'hello'), StoredFileCategory::PRIVATE);
        self::assertNotNull($upload);

        $this->actingAs($user);
        $message = $this->app->make(PrivateMessageHandler::class)
            ->create($this->createConversation($user), $this->privateMessageData($upload->getUuid()), $user);

        self::assertSame([$upload->getUuid()], $message->attachments()->pluck('uuid')->all());
    }

    // =========================================================================

    /**
     * @return string The uuid of a real, persisted file, so the handler would be able to link it.
     */
    private function storeFile(StoredFileCategory $category): string
    {
        $storedFile = $this->app->make(FileStorageService::class)
            ->store(FileReference::fromContent('secret.txt', 'top secret'), $category);
        self::assertNotNull($storedFile);

        return $storedFile->getUuid();
    }

    private function createConversation(User $owner): AiConv
    {
        return AiConv::query()->create([
            'conv_name' => 'Own',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $owner->id,
            'system_prompt' => null,
        ]);
    }

    private function memberOf(Room $room, User $user): Member
    {
        return Member::withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    private function privateMessageData(string $uuid): array
    {
        return [
            'isAi' => false,
            'threadId' => 0,
            'completion' => true,
            'content' => [
                'text' => ['ciphertext' => 'encrypted', 'iv' => 'iv', 'tag' => 'tag'],
                'attachments' => [$uuid],
            ],
        ];
    }

    private function groupMessageData(Member $member, string $uuid): array
    {
        return [
            'member' => $member,
            'message_role' => 'user',
            'threadId' => 0,
            'content' => [
                'text' => ['ciphertext' => 'encrypted', 'iv' => 'iv', 'tag' => 'tag'],
                'attachments' => [$uuid],
            ],
        ];
    }
}
