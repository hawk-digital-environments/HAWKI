<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * Thread replies ride the legacy `W.DDD` id scheme: the AI answer to a thread
 * reply gets the thread's next decimal id, and a regenerated answer is
 * updated in place — switching the model and the metadata while keeping the
 * id, so the thread stays grouped after a reload. That persistence contract
 * is what the frontend's regenerate flow (`ChatTransport.regenerateMessage`)
 * writes through.
 */
#[CoversNothing()]
class ThreadMessageIdPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function testThreadAiRepliesGetThreadMessageIds(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);

        // Trunk turn, then the user's thread reply.
        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(0),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.000');

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.001');

        // The AI answer to the thread reply stays in the thread.
        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1, isAi: true),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.002');
    }

    public function testUpdatingAThreadAiReplySwitchesTheModelInPlace(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(0),
            $this->jsonApiHeaders(),
        )->assertSuccessful();

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1, isAi: true),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.001');

        // The regenerate write path: same id, switched model and metadata.
        $this->actingAs($user)->patchJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages/1.001",
            [
                'isAi' => true,
                'message_id' => '1.001',
                'content' => [
                    'text' => ['ciphertext' => 're-encrypted', 'iv' => 'iv-2', 'tag' => 'tag-2'],
                    'attachments' => [],
                ],
                'metadata' => ['tools' => [], 'params' => null],
                'model' => 'switched-thread-model',
                'completion' => true,
            ],
            $this->jsonApiHeaders(),
        // The regenerate write path: same id, switched model and metadata. (The
        // action answers 200 or 201 depending on the storage path — the
        // invariant under test is the in-place id, not the status code.)
        )->assertSuccessful()->assertJsonPath('data.attributes.message_id', '1.001');

        $stored = AiConvMsg::query()->where('message_id', '1.001')->sole();
        self::assertSame('switched-thread-model', $stored->model);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageBody(int $threadId, bool $isAi = false): array
    {
        return [
            'isAi' => $isAi,
            'threadId' => $threadId,
            'content' => [
                'text' => ['ciphertext' => 'encrypted', 'iv' => 'iv', 'tag' => 'tag'],
                'attachments' => [],
            ],
            'metadata' => ['tools' => [], 'params' => null],
            'model' => $isAi ? 'assistant-model' : null,
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
