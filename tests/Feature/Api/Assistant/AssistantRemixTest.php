<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantTag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

#[CoversNothing()]
class AssistantRemixTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    public function testCanRemixAssistant(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();
        $originalCreator = User::factory()->create();

        $tool = $this->createAiTool();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'remixed_creator_id' => $originalCreator->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->assistantUserPrompts()->createMany([
            ['text' => 'Prompt one'],
            ['text' => 'Prompt two'],
        ]);
        $assistant->ai_tools()->attach($tool->id);
        $assistant->assistantTags()->attach(AssistantTag::create(['text' => 'remix-tag']));
        $assistant->attachments()->create([
            'uuid' => 'test-uuid',
            'name' => 'test.png',
            'category' => 'avatar',
            'type' => 'image',
            'mime' => 'image/png',
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($remixUser);

        $response = $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertNotNull($clone);
        self::assertEquals($remixUser->id, $clone->creator_id);
        self::assertEquals($owner->id, $clone->remixed_creator_id);
        self::assertNotEquals($assistant->id, $clone->id);
        self::assertEquals($assistant->id, $clone->remixed_assistant_id);
        self::assertSame(\App\Services\Assistant\Values\AssistantReleaseStage::PRIVATE, $clone->release_stage);

        self::assertEquals($assistant->name, $clone->name);
        self::assertEquals($assistant->system_prompt, $clone->system_prompt);
        self::assertEquals($assistant->description, $clone->description);
        self::assertEquals($assistant->greeting, $clone->greeting);
        self::assertEquals($assistant->allow_remix, $clone->allow_remix);
        self::assertEquals($assistant->allow_model_select, $clone->allow_model_select);
        self::assertEquals($assistant->max_tokens, $clone->max_tokens);
        self::assertEquals($assistant->temp, $clone->temp);
        self::assertEquals($assistant->top_p, $clone->top_p);
        self::assertEquals($assistant->model, $clone->model);
        self::assertEquals($assistant->detail_description, $clone->detail_description);
        self::assertEquals($assistant->category_id, $clone->category_id);

        self::assertEquals(
            $assistant->settingValues()->count(),
            $clone->settingValues()->count(),
        );

        foreach ($assistant->settingValues as $value) {
            self::assertTrue(
                $clone->settingValues()
                    ->where('setting_id', $value->setting_id)
                    ->where('value', $value->value)
                    ->exists(),
            );
        }

        self::assertNull($clone->handle);

        self::assertEquals(2, $clone->assistantUserPrompts()->count());
        self::assertTrue($clone->assistantTags()->where('text', 'remix-tag')->exists());
        self::assertEquals(1, $clone->attachments()->count());
        self::assertEquals('test-uuid', $clone->attachments()->first()->uuid);

        $response->assertJson([
            'data' => [
                'id' => (string) $clone->id,
                'attributes' => [
                    'is_favorite' => false,
                ],
            ],
        ]);
    }

    public function testRemixCopiesLatestVersionOnly(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        // version is intentionally not mass-assignable on AssistantVersion.
        // The factory already seeds an initial v1.0; append newer versions.
        $assistant->assistantVersions()->saveMany([
            (new \App\Models\Assistants\AssistantVersion())->forceFill(['text' => 'Version 2', 'version' => 2.0]),
            (new \App\Models\Assistants\AssistantVersion())->forceFill(['text' => 'Version 3', 'version' => 3.0]),
        ]);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();

        self::assertEquals(1, $clone->assistantVersions()->count());
        $latestVersion = $clone->assistantVersions()->first();
        self::assertEquals('Version 3', $latestVersion->text);
        self::assertEquals(3.0, (float) $latestVersion->version);
    }

    public function testRemixCopiesAiToolsWhenUsersShareOrganization(): void
    {
        $org = Organization::create(['name' => 'Test Org']);
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();
        $org->users()->attach([$owner->id, $remixUser->id]);

        $tool = $this->createAiTool();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->ai_tools()->attach($tool->id);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertTrue($clone->ai_tools()->where('ai_tool_id', $tool->id)->exists());
    }

    public function testRemixDoesNotCopyAiToolsWhenUsersDifferOrgs(): void
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
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->ai_tools()->attach($tool->id);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertFalse($clone->ai_tools()->exists());
    }

    public function testRemixCopiesAttachments(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
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

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertEquals(2, $clone->attachments()->count());
        self::assertTrue($clone->attachments()->where('uuid', 'file-1')->exists());
        self::assertTrue($clone->attachments()->where('uuid', 'file-2')->exists());
    }

    public function testRemixDoesNotCopyReviews(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->assistantReview()->create([
            'status' => 'approved',
            'reason' => 'Looks good',
        ]);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertNull($clone->assistantReview);
    }

    public function testRemixSetReleaseStageToPrivate(): void
    {
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => 'federated',
        ]);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertSame(\App\Services\Assistant\Values\AssistantReleaseStage::PRIVATE, $clone->release_stage);
    }

    public function testRemixedCreatorIdIsSourceCreatorId(): void
    {
        $originalCreator = User::factory()->create();
        $owner = User::factory()->create();
        $remixUser = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'remixed_creator_id' => $originalCreator->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->actingAsUser($remixUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        $clone = Assistant::where('creator_id', $remixUser->id)->first();
        self::assertEquals($owner->id, $clone->remixed_creator_id);
    }

    public function testCannotRemixWhenNotAllowed(): void
    {
        $owner = User::factory()->create();
        $remixer = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => false,
        ]);

        $this->actingAsUser($remixer);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);

        self::assertEquals(1, Assistant::count());
    }

    /**
     * Even with allow_remix = true, a non-owner cannot remix a private/draft
     * assistant: the remix policy requires view permission on the source so
     * that knowing an id is not enough to clone an unpublished assistant.
     */
    public function testCannotRemixPrivateAssistantWhenNotViewer(): void
    {
        $owner = User::factory()->create();
        $remixer = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($remixer);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertForbidden();

        self::assertEquals(1, Assistant::count());
    }

    public function testGuestCannotRemixAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
        ]);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertStatus(403);
    }

    public function testRemixSkipsVersionOnSourceWhenPrivate(): void
    {
        // Self-remix: the creator can remix their own private assistant (view
        // always passes for the creator), and the source's version history is
        // untouched because private-stage updates don't bump versions.
        $owner = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testRemixSkipsVersionOnSourceWhenDraft(): void
    {
        $owner = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'allow_remix' => true,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/remix")
            ->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }
}
