<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantActionLinksTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerSeesDistinctPerRelationActionLinks(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);

        $this->actingAsUser($owner);

        $base = config('app.url') . "/api/hawki/v1/assistants/{$assistant->id}";

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'links' => [
                        // Three distinct detach links must coexist (no collision).
                        'detachSharedUsers' => [
                            'href' => "{$base}/relationships/shared-users",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        'detachAssistantTags' => [
                            'href' => "{$base}/relationships/assistant-tags",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        'detachAiTools' => [
                            'href' => "{$base}/relationships/ai-tools",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        // Attach / update verbs resolve to the correct relation too.
                        'attachSharedUsers' => [
                            'href' => "{$base}/relationships/shared-users",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        'updateAssistantTags' => [
                            'href' => "{$base}/relationships/assistant-tags",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        // Read verb ("show") maps to the "view*" ability.
                        'viewAiTools' => [
                            'href' => "{$base}/relationships/ai-tools",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        // Custom /actions/ routes keep working.
                        'remix' => [
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                    ],
                ],
            ]);
    }

    public function testNonOwnerSeesDeniedRelationshipActionLinks(): void
    {
        $owner = User::factory()->create();
        // organisational => visible to the other user, but writes are owner-only.
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $base = config('app.url') . "/api/hawki/v1/assistants/{$assistant->id}";

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'links' => [
                        'detachSharedUsers' => [
                            'href' => "{$base}/relationships/shared-users",
                            'meta' => ['message' => 'DENIED'],
                        ],
                        'attachAiTools' => [
                            'href' => "{$base}/relationships/ai-tools",
                            'meta' => ['message' => 'DENIED'],
                        ],
                    ],
                ],
            ]);
    }
}
