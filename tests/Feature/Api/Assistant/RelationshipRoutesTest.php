<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Feedback;
use App\Models\Assistants\Tag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelationshipRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_tags_related_url_is_visible_to_any_viewer(): void
    {
        [$assistant, $users] = $this->stakeholders();
        $tag = Tag::create(['text' => 'php']);
        $assistant->tags()->attach($tag);

        $url = "/api/assistants/{$assistant->id}/assistant-tags";

        // The assistant is public (organizational), so every viewer passes
        // `view`, and tags are public-tier — everyone gets 200.
        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $this->jsonApi('get', $url)->assertOk();
        }
    }

    public function test_assistant_feedback_related_url_is_privileged(): void
    {
        [$assistant, $users] = $this->stakeholders();
        Feedback::create([
            'assistant_id' => $assistant->id,
            'user_id' => $assistant->creator_id,
            'text' => 'nice',
        ]);

        $url = "/api/assistants/{$assistant->id}/assistant-feedback";

        Sanctum::actingAs($users['owner']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['admin']);
        $this->jsonApi('get', $url)->assertOk();

        foreach (['member', 'shared', 'public'] as $role) {
            Sanctum::actingAs($users[$role]);
            $this->jsonApi('get', $url)->assertForbidden();
        }
    }

    public function test_tag_assistant_link_does_not_leak_private_assistant(): void
    {
        // Tags are a global library (many-to-many); a tag no longer exposes a
        // single parent `assistant` link, so this leak vector no longer exists.
        $this->markTestSkipped('Tags are global library labels; no parent assistant link to guard.');
    }

    public function test_user_prompt_assistant_link_does_not_leak_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $prompt = $assistant->user_prompts()->create(['text' => 'hi']);

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistant-user-prompts/{$prompt->id}/assistant")
            ->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->jsonApi('get', "/api/assistant-user-prompts/{$prompt->id}/assistant")
            ->assertForbidden();
    }

    public function test_feedback_assistant_and_user_links_do_not_leak_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $feedback = Feedback::create([
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'nice',
        ]);

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistant-feedback/{$feedback->id}/assistant")->assertOk();
        $this->jsonApi('get', "/api/assistant-feedback/{$feedback->id}/user")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->jsonApi('get', "/api/assistant-feedback/{$feedback->id}/assistant")->assertForbidden();
        $this->jsonApi('get', "/api/assistant-feedback/{$feedback->id}/user")->assertForbidden();
    }

    public function test_assistant_show_advertises_resolvable_tags_related_link(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $tag = Tag::create(['text' => 'php']);
        $assistant->tags()->attach($tag);

        Sanctum::actingAs($owner);

        $show = $this->jsonApi('get', "/api/assistants/{$assistant->id}")->assertOk();
        $related = $show->json('data.relationships.assistant_tags.links.related');

        $this->assertNotNull($related, 'assistant_tags related link must be advertised');
        $this->jsonApi('get', $related)->assertOk();
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
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
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
