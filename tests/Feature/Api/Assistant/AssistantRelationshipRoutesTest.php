<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFeedback;
use App\Models\Assistants\AssistantTag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantRelationshipRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function testAssistantTagsRelatedUrlIsVisibleToAnyViewer(): void
    {
        [$assistant, $users] = $this->stakeholders();
        $tag = AssistantTag::create(['text' => 'php']);
        $assistant->assistantTags()->attach($tag);

        $url = "/api/hawki/v1/assistants/{$assistant->id}/assistant-tags";

        // The assistant is public (organizational), so every viewer passes
        // `view`, and tags are public-tier — everyone gets 200.
        foreach ($users as $user) {
            $this->actingAsUser($user);
            $this->jsonApiRaw('get', $url)->assertOk();
        }
    }

    public function testAssistantFeedbackRelatedUrlIsPrivileged(): void
    {
        [$assistant, $users] = $this->stakeholders();
        AssistantFeedback::forceCreate([
            'assistant_id' => $assistant->id,
            'user_id' => $assistant->creator_id,
            'text' => 'nice',
        ]);

        $url = "/api/hawki/v1/assistants/{$assistant->id}/assistant-feedback";

        $this->actingAsUser($users['owner']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['admin']);
        $this->jsonApiRaw('get', $url)->assertOk();

        foreach (['member', 'shared', 'public'] as $role) {
            $this->actingAsUser($users[$role]);
            $this->jsonApiRaw('get', $url)->assertStatus(403);
        }
    }

    public function testUserPromptRelationshipRoutesAreNotExposed(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'hi']);

        // assistant-user-prompts is write-only: the assistant relationship is set
        // on create but exposes no read endpoints, so both related and relationship
        // URLs are gone (404) regardless of who is asking.
        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}/assistant")
            ->assertNotFound();

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}/assistant")
            ->assertNotFound();
    }

    public function testFeedbackRelationshipRoutesAreNotExposed(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $feedback = AssistantFeedback::forceCreate([
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'nice',
        ]);

        // assistant-feedback is write-only: neither the assistant nor the user
        // relationship exposes read endpoints, so the routes do not exist.
        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-feedback/{$feedback->id}/assistant")->assertNotFound();
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-feedback/{$feedback->id}/user")->assertNotFound();

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-feedback/{$feedback->id}/assistant")->assertNotFound();
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-feedback/{$feedback->id}/user")->assertNotFound();
    }

    public function testAssistantShowAdvertisesResolvableTagsRelatedLink(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $tag = AssistantTag::create(['text' => 'php']);
        $assistant->assistantTags()->attach($tag);

        $this->actingAsUser($owner);

        $show = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")->assertOk();
        $related = $show->json('data.relationships.assistant_tags.links.related');

        self::assertNotNull($related, 'assistant_tags related link must be advertised');
        $this->jsonApiRaw('get', $related)->assertOk();
    }

    /**
     * @return array{0: Assistant, 1: array<string, User>}
     */
    private function stakeholders(): array
    {
        $org = Organization::first();

        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($assistant->organization_id, ['role' => 'admin']);

        $member = User::factory()->create();
        $member->organizations()->attach($assistant->organization_id, ['role' => 'member']);

        $shared = User::factory()->create();
        $assistant->sharedUsers()->sync([$shared->id]);

        $public = User::factory()->create();

        return [
            $assistant,
            ['owner' => $owner, 'admin' => $admin, 'member' => $member, 'shared' => $shared, 'public' => $public],
        ];
    }
}
