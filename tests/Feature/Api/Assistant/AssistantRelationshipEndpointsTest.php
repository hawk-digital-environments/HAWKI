<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

#[CoversNothing()]
class AssistantRelationshipEndpointsTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    public function testOwnerCanAttachAndDetachAiTools(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $tool = $this->createAiTool();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_tools', [
            'assistant_id' => $assistant->id,
            'ai_tool_id' => $tool->id,
        ]);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_tools', [
            'assistant_id' => $assistant->id,
            'ai_tool_id' => $tool->id,
        ]);
    }

    public function testNonOwnerCannotAttachAiTools(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $tool = $this->createAiTool();

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertForbidden();
    }

    public function testOwnerCanAttachAndDetachAssistantTags(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $tag = AssistantTag::create(['text' => 'php']);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_tag', [
            'assistant_id' => $assistant->id,
            'tag_id' => $tag->id,
        ]);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_tag', [
            'assistant_id' => $assistant->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function testNonPrivilegedUserCannotAttachAssistantTags(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $tag = AssistantTag::create(['text' => 'php']);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/assistant-tags", [
            'data' => [['type' => 'assistant-tags', 'id' => (string) $tag->id]],
        ])->assertForbidden();
    }
}
