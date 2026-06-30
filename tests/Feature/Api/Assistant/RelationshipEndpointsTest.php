<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class RelationshipEndpointsTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    public function test_owner_can_attach_and_detach_ai_tools(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $tool = $this->createAiTool();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_tools', [
            'assistant_id' => $assistant->id,
            'ai_tool_id' => $tool->id,
        ]);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_tools', [
            'assistant_id' => $assistant->id,
            'ai_tool_id' => $tool->id,
        ]);
    }

    public function test_non_owner_cannot_attach_ai_tools(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $tool = $this->createAiTool();

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertForbidden();
    }

    public function test_owner_can_attach_and_detach_assistant_tags(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $tag = Tag::create(['text' => 'php']);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_tag', [
            'assistant_id' => $assistant->id,
            'tag_id' => $tag->id,
        ]);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_tag', [
            'assistant_id' => $assistant->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_non_privileged_user_cannot_attach_assistant_tags(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $tag = Tag::create(['text' => 'php']);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertForbidden();
    }
}
