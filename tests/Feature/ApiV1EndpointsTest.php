<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class ApiV1EndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function testConversationCreationAssignsTheAuthenticatedOwnerAndServerGeneratedSlug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/ai-convs',
            [
                'data' => [
                    'type' => 'ai-convs',
                    'attributes' => [
                        'name' => 'Review test',
                        'system_prompt' => null,
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Review test');

        $conversation = AiConv::query()->sole();
        self::assertSame($user->id, $conversation->user_id);
        self::assertNotSame('', $conversation->slug);
    }

    public function testMessageAuthorizationUsesTheJsonApiRouteModel(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => 'private-conversation',
            'user_id' => $owner->id,
            'system_prompt' => null,
        ]);

        $this->actingAs($otherUser)
            ->postJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
                [],
                $this->jsonApiHeaders(),
            )
            ->assertForbidden();
    }

    public function testStoredMessageIncludesItsAuthorAsARelationship(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => 'private-conversation',
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);

        $this->actingAs($user)
            ->postJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
                [
                    'isAi' => false,
                    'threadId' => 0,
                    'content' => [
                        'text' => [
                            'ciphertext' => 'encrypted',
                            'iv' => 'iv',
                            'tag' => 'tag',
                        ],
                        'attachments' => [],
                    ],
                    'metadata' => null,
                    'model' => null,
                    'completion' => true,
                ],
                $this->jsonApiHeaders(),
            )
            ->assertCreated()
            ->assertJsonPath('data.relationships.author.data.type', 'users')
            ->assertJsonPath('data.relationships.author.data.id', (string) $user->id)
            ->assertJsonPath('included.0.attributes.username', $user->username);
    }

    public function testConversationCanIncludeMessagesTheirAuthorsAndAttachments(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => 'private-conversation',
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);
        $message = AiConvMsg::query()->create([
            'conv_id' => $conversation->id,
            'user_id' => $user->id,
            'message_role' => 'user',
            'message_id' => '1',
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
            'completion' => true,
        ]);
        $message->attachments()->create([
            'ai_conv_msg_id' => $message->id,
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'name' => 'review.txt',
            'category' => 'private',
            'type' => 'document',
            'mime' => 'text/plain',
            'user_id' => $user->id,
        ]);

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

    public function testDeletingConversationRemovesItsPolymorphicAttachments(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Private',
            'slug' => 'private-conversation',
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);
        $message = AiConvMsg::query()->create([
            'conv_id' => $conversation->id,
            'user_id' => $user->id,
            'message_role' => 'user',
            'message_id' => '1',
            'iv' => 'iv',
            'tag' => 'tag',
            'content' => 'encrypted',
            'completion' => true,
        ]);
        $attachment = $message->attachments()->create([
            'ai_conv_msg_id' => $message->id,
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'name' => 'review.txt',
            'category' => 'private',
            'type' => 'document',
            'mime' => 'text/plain',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}",
                [],
                $this->jsonApiHeaders(),
            )
            ->assertNoContent();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('ai_conv_msgs', ['id' => $message->id]);
        $this->assertDatabaseMissing('ai_convs', ['id' => $conversation->id]);
    }

    public function testLocaleActionPersistsThePreferenceOnTheCurrentUser(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->postJson(
                '/api/hawki/v1/users/actions/locale',
                ['locale' => 'de_DE'],
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('locale', 'de_DE');

        self::assertSame('de_DE', $user->refresh()->locale);
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
