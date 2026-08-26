<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\User;
use App\Services\Storage\TemporaryUploadOwnership;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class ApiV1EndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function testItAssignsTheAuthenticatedOwnerAndAServerGeneratedSlugOnConversationCreation(): void
    {
        $user = User::factory()->create();
        $payload = ['data' => ['type' => 'ai-convs', 'attributes' => ['name' => 'Review test', 'system_prompt' => null]]];

        $this->actingAs($user)
            ->postJson('/api/hawki/v1/ai-convs', $payload, $this->jsonApiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Review test');

        $conversation = AiConv::query()->where('user_id', $user->id)->sole();
        self::assertSame($user->id, $conversation->user_id);
        self::assertNotSame('', $conversation->slug);
    }

    public function testItForbidsPostingMessagesToAnotherUsersConversation(): void
    {
        $conversation = $this->createConversation(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson($this->messagesActionUrl($conversation), [], $this->jsonApiHeaders())
            ->assertForbidden();
    }

    public function testItIncludesTheAuthorAsARelationshipOnStoredMessages(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);

        $this->actingAs($user)
            ->postJson($this->messagesActionUrl($conversation), $this->messagePayload(), $this->jsonApiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.relationships.author.data.type', 'users')
            ->assertJsonPath('data.relationships.author.data.id', (string) $user->id)
            ->assertJsonPath('included.0.attributes.username', $user->username);
    }

    public function testItRequiresEncryptedTextWhenStoringAMessage(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $payload = $this->messagePayload();
        unset($payload['content']['text']);

        $this->actingAs($user)
            ->postJson($this->messagesActionUrl($conversation), $payload, $this->jsonApiHeaders())
            ->assertUnprocessable();

        self::assertDatabaseMissing('ai_conv_msgs', ['conv_id' => $conversation->id]);
    }

    public function testItRejectsATemporaryAttachmentUploadedByAnotherUser(): void
    {
        $uploader = User::factory()->create();
        $requestingUser = User::factory()->create();
        $conversation = $this->createConversation($requestingUser);
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(
            StoredFileCategory::PRIVATE,
            '00000000-0000-0000-0000-000000000002',
        );
        $this->app->make(TemporaryUploadOwnership::class)->register($identifier, $uploader);
        $payload = $this->messagePayload();
        $payload['content']['attachments'] = [$identifier->uuid];

        $this->actingAs($requestingUser)
            ->postJson($this->messagesActionUrl($conversation), $payload, $this->jsonApiHeaders())
            ->assertForbidden();

        self::assertDatabaseMissing('ai_conv_msgs', ['conv_id' => $conversation->id]);
    }

    public function testItRejectsAnUnknownOrExpiredTemporaryAttachment(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $payload = $this->messagePayload();
        $payload['content']['attachments'] = ['00000000-0000-0000-0000-000000000003'];

        $this->actingAs($user)
            ->postJson($this->messagesActionUrl($conversation), $payload, $this->jsonApiHeaders())
            ->assertUnprocessable();

        self::assertDatabaseMissing('ai_conv_msgs', ['conv_id' => $conversation->id]);
    }

    public function testItAttachesATemporaryUploadOfTheCurrentUserToAStoredMessage(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $ownership = $this->app->make(TemporaryUploadOwnership::class);

        $uuid = $this->actingAs($user)
            ->post(
                '/api/hawki/v1/ai-convs/actions/attachments',
                ['file' => UploadedFile::fake()->createWithContent('note.txt', 'hello')],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->json('uuid');
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
        self::assertTrue($ownership->isOwnedBy($identifier, $user));

        $payload = $this->messagePayload();
        $payload['content']['attachments'] = [$uuid];

        $this->actingAs($user)
            ->postJson($this->messagesActionUrl($conversation), $payload, $this->jsonApiHeaders())
            ->assertCreated()
            ->assertJsonCount(1, 'data.relationships.attachments.data');

        self::assertDatabaseHas('attachments', ['uuid' => $uuid, 'user_id' => $user->id]);
        self::assertFalse($ownership->isRegistered($identifier));

        // Remove the message again so the persisted file does not linger on disk after the DB rollback.
        $messageId = AiConvMsg::query()->where('conv_id', $conversation->id)->sole()->message_id;
        $this->actingAs($user)
            ->deleteJson($this->messagesActionUrl($conversation) . '/' . $messageId, [], $this->jsonApiHeaders())
            ->assertNoContent();
    }

    public function testItCanIncludeMessagesTheirAuthorsAndAttachmentsOnAConversation(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $this->createAttachment($this->createMessage($conversation, $user), $user);

        $this->actingAs($user)
            ->getJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}?include=messages.author,messages.attachments",
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.relationships.messages.data.0.type', 'ai-conv-messages')
            ->assertJsonPath('included.0.attributes.completion', true)
            ->assertJsonFragment(['username' => $user->username])
            ->assertJsonFragment(['name' => 'review.txt', 'mime' => 'text/plain']);
    }

    public function testItRemovesPolymorphicAttachmentsWhenDeletingAConversation(): void
    {
        $user = User::factory()->create();
        $conversation = $this->createConversation($user);
        $message = $this->createMessage($conversation, $user);
        $attachment = $this->createAttachment($message, $user);

        $this->actingAs($user)
            ->deleteJson("/api/hawki/v1/ai-convs/{$conversation->slug}", [], $this->jsonApiHeaders())
            ->assertNoContent();

        self::assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        self::assertDatabaseMissing('ai_conv_msgs', ['id' => $message->id]);
        self::assertDatabaseMissing('ai_convs', ['id' => $conversation->id]);
    }

    public function testItPersistsTheLocalePreferenceOnTheCurrentUser(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->postJson('/api/hawki/v1/users/actions/locale', ['locale' => 'de_DE'], $this->jsonApiHeaders())
            ->assertOk()
            ->assertJsonPath('locale', 'de_DE');

        self::assertSame('de_DE', $user->refresh()->locale);
    }

    // =========================================================================

    private function createConversation(User $owner): AiConv
    {
        return AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => 'private-conversation',
            'user_id' => $owner->id,
            'system_prompt' => null,
        ]);
    }

    private function createMessage(AiConv $conversation, User $user): AiConvMsg
    {
        return AiConvMsg::query()->create([
            'conv_id' => $conversation->id,
            'user_id' => $user->id,
            'message_role' => 'user',
            'message_id' => '1',
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
            'completion' => true,
        ]);
    }

    private function createAttachment(AiConvMsg $message, User $user): Attachment
    {
        return $message->attachments()->create([
            'ai_conv_msg_id' => $message->id,
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'name' => 'review.txt',
            'category' => 'private',
            'type' => 'document',
            'mime' => 'text/plain',
            'user_id' => $user->id,
        ]);
    }

    private function messagesActionUrl(AiConv $conversation): string
    {
        return "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages";
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(): array
    {
        return [
            'isAi' => false,
            'threadId' => 0,
            'content' => [
                'text' => ['ciphertext' => 'encrypted', 'iv' => 'iv', 'tag' => 'tag'],
                'attachments' => [],
            ],
            'metadata' => null,
            'model' => null,
            'completion' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json,application/json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
