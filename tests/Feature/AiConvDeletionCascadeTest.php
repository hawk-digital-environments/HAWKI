<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AiConvDeletionCascadeTest extends TestCase
{
    use DatabaseTransactions;

    public function testItCascadesMessagesAndAttachmentsWhenTheConversationRowIsDeleted(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $uuids = [
            $this->createMessageWithAttachment($conversation, $user, 1),
            $this->createMessageWithAttachment($conversation, $user, 2),
        ];

        DB::table('ai_convs')->where('id', $conversation->id)->delete();

        static::assertDatabaseMissing('ai_convs', ['id' => $conversation->id]);
        static::assertDatabaseMissing('ai_conv_msgs', ['conv_id' => $conversation->id]);
        foreach ($uuids as $uuid) {
            static::assertDatabaseMissing('attachments', ['uuid' => $uuid]);
        }
    }

    public function testTheDatabaseDerivesThePrivateMessageForeignKey(): void
    {
        $user = User::factory()->create();
        $message = $this->createMessage($this->createConversation($user), $user, 1);

        $attachment = $message->attachments()->create($this->attachmentAttributes($user, (string) Str::uuid(), 'plain.txt'));

        self::assertSame($message->id, $attachment->fresh()->ai_conv_msg_id);
    }

    public function testItLeavesTheForeignKeyEmptyForGroupChatAttachments(): void
    {
        $user = User::factory()->create();

        // A room message needs no real row here: `attachable_id` carries no foreign
        // key, and the point is only that nothing gets mirrored for `Message`.
        $attachment = new Attachment(['category' => 'group'] + $this->attachmentAttributes($user, (string) Str::uuid(), 'room.txt'));
        $attachment->attachable_type = Message::class;
        $attachment->attachable_id = \PHP_INT_MAX;
        $attachment->save();

        self::assertNull($attachment->fresh()->ai_conv_msg_id);
    }

    // =========================================================================

    private function createConversation(User $user): AiConv
    {
        return AiConv::query()->create([
            'conv_name' => 'Cascade test',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);
    }

    private function createMessage(AiConv $conversation, User $user, int $index): AiConvMsg
    {
        return AiConvMsg::query()->create([
            'conv_id' => $conversation->id,
            'user_id' => $user->id,
            'message_role' => 'user',
            'message_id' => (string) $index,
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
            'completion' => true,
        ]);
    }

    /**
     * @return string The attachment uuid.
     */
    private function createMessageWithAttachment(AiConv $conversation, User $user, int $index): string
    {
        $message = $this->createMessage($conversation, $user, $index);
        $uuid = (string) Str::uuid();
        $message->attachments()->create($this->attachmentAttributes($user, $uuid, "cascade-{$index}.txt"));

        return $uuid;
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentAttributes(User $user, string $uuid, string $name): array
    {
        return [
            'uuid' => $uuid,
            'name' => $name,
            'category' => 'private',
            'type' => 'document',
            'mime' => 'text/plain',
            'user_id' => $user->id,
        ];
    }
}
