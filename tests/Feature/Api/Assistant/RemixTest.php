<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Tag;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class RemixTest extends TestCase
{
    use AssistantFixture, RefreshDatabase;

    public function test_can_remix_assistant(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();
        $originalCreator = User::factory()->create();

        $tool = $this->createAiTool();
        $tag = Tag::create(['text' => 'remix-tag']);

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'remixed_creator_id' => $originalCreator->id,
            'allow_remix' => true,
        ]);
        $assistant->user_prompts()->createMany([
            ['text' => 'Prompt one'],
            ['text' => 'Prompt two'],
        ]);
        $assistant->ai_tools()->attach($tool->id);
        $assistant->tags()->attach($tag->id);
        $assistant->attachments()->create([
            'uuid' => 'test-uuid',
            'name' => 'test.png',
            'category' => 'avatar',
            'type' => 'image',
            'mime' => 'image/png',
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($remixUser);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertNotNull($clone);
        $this->assertEquals($remixUser->id, $clone->creator_id);
        $this->assertEquals($owner->id, $clone->remixed_creator_id);
        $this->assertNotEquals($assistant->id, $clone->id);
        $this->assertEquals($assistant->id, $clone->remixed_assistant_id);
        $this->assertEquals('private', $clone->release_stage);

        $this->assertEquals($assistant->name, $clone->name);
        $this->assertEquals($assistant->system_prompt, $clone->system_prompt);
        $this->assertEquals($assistant->description, $clone->description);
        $this->assertEquals($assistant->greeting, $clone->greeting);
        $this->assertEquals($assistant->allow_remix, $clone->allow_remix);
        $this->assertEquals($assistant->allow_model_select, $clone->allow_model_select);
        $this->assertEquals($assistant->max_tokens, $clone->max_tokens);
        $this->assertEquals($assistant->temp, $clone->temp);
        $this->assertEquals($assistant->top_p, $clone->top_p);
        $this->assertEquals($assistant->model, $clone->model);
        $this->assertEquals($assistant->formality, $clone->formality);
        $this->assertEquals($assistant->detail_description, $clone->detail_description);
        $this->assertEquals($assistant->language_id, $clone->language_id);
        $this->assertEquals($assistant->category_id, $clone->category_id);

        $this->assertNull($clone->handle);

        $this->assertEquals(2, $clone->user_prompts()->count());
        $this->assertTrue($clone->tags()->where('tag_id', $tag->id)->exists());
        $this->assertEquals(1, $clone->attachments()->count());
        $this->assertEquals('test-uuid', $clone->attachments()->first()->uuid);

        $response->assertJson([
            'data' => [
                'id' => (string) $clone->id,
            ],
        ]);
    }

    public function test_remix_copies_latest_version_only(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);
        $assistant->versions()->createMany([
            ['text' => 'Version 1', 'version' => 1.0],
            ['text' => 'Version 2', 'version' => 2.0],
            ['text' => 'Version 3', 'version' => 3.0],
        ]);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();

        $this->assertEquals(1, $clone->versions()->count());
        $latestVersion = $clone->versions()->first();
        $this->assertEquals('Version 3', $latestVersion->text);
        $this->assertEquals(3.0, (float) $latestVersion->version);
    }

    public function test_remix_copies_ai_tools_when_users_share_organization(): void
    {
        $org = Organization::create(['name' => 'Test Org']);
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();
        $org->users()->attach([$owner->id, $remixUser->id]);

        $tool = $this->createAiTool();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);
        $assistant->ai_tools()->attach($tool->id);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertTrue($clone->ai_tools()->where('ai_tool_id', $tool->id)->exists());
    }

    public function test_remix_does_not_copy_ai_tools_when_users_differ_orgs(): void
    {
        $org1 = Organization::create(['name' => 'Org 1']);
        $org2 = Organization::create(['name' => 'Org 2']);
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();
        $org1->users()->attach($owner->id);
        $org2->users()->attach($remixUser->id);

        $tool = $this->createAiTool();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);
        $assistant->ai_tools()->attach($tool->id);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertFalse($clone->ai_tools()->exists());
    }

    public function test_remix_copies_attachments(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);
        $assistant->attachments()->createMany([
            [
                'uuid' => 'file-1',
                'name' => 'doc.pdf',
                'category' => 'document',
                'type' => 'document',
                'mime' => 'application/pdf',
                'user_id' => $owner->id,
            ],
            [
                'uuid' => 'file-2',
                'name' => 'img.png',
                'category' => 'avatar',
                'type' => 'image',
                'mime' => 'image/png',
                'user_id' => $owner->id,
            ],
        ]);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertEquals(2, $clone->attachments()->count());
        $this->assertTrue($clone->attachments()->where('uuid', 'file-1')->exists());
        $this->assertTrue($clone->attachments()->where('uuid', 'file-2')->exists());
    }

    public function test_remix_does_not_copy_reviews(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);
        $assistant->review()->create([
            'status' => 'approved',
            'reason' => 'Looks good',
        ]);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertNull($clone->review);
    }

    public function test_remix_set_release_stage_to_private(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => 'federated',
        ]);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertEquals('private', $clone->release_stage);
    }

    public function test_remixed_creator_id_is_source_creator_id(): void
    {
        $originalCreator = User::factory()->create();
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'remixed_creator_id' => $originalCreator->id,
            'allow_remix' => true,
        ]);

        Sanctum::actingAs($remixUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        $this->assertEquals($owner->id, $clone->remixed_creator_id);
    }

    public function test_cannot_remix_when_not_allowed(): void
    {
        $owner = User::factory()->create();
        $remixer = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => false,
        ]);

        Sanctum::actingAs($remixer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);

        $this->assertEquals(1, Assistant::count());
    }

    public function test_guest_cannot_remix_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/remix")
            ->assertUnauthorized();
    }
}
