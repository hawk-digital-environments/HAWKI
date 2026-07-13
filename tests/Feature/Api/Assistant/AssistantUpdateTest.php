<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantUpdated;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use App\Services\Assistant\Listeners\AssistantResetReviewOnUpdate;
use App\Services\Assistant\Listeners\AssistantUpdatedVersion;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

#[CoversNothing()]
class AssistantUpdateTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
    }

    public function testCanUpdateAssistant(): void
    {
        Event::fake(AssistantUpdated::class);
        Event::assertListening(AssistantUpdated::class, AssistantUpdatedVersion::class);
        Event::assertListening(AssistantUpdated::class, AssistantResetReviewOnUpdate::class);

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id, 'remixed_creator_id' => null]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
            'description' => 'Updated description.',
        ]))
            ->assertOk();

        $assistant->refresh();
        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'type' => 'assistants',
                'attributes' => [
                    'name' => 'Updated Name',
                    'description' => 'Updated description.',
                    'handle' => $assistant->handle,
                    'system_prompt' => $assistant->system_prompt,
                    'greeting' => $assistant->greeting,
                    'detail_description' => $assistant->detail_description,
                    'allow_remix' => (int) $assistant->allow_remix,
                    'allow_model_select' => (int) $assistant->allow_model_select,
                    'release_stage' => $assistant->release_stage,
                    'model' => $assistant->model,
                    'max_tokens' => $assistant->max_tokens,
                    'temp' => $assistant->temp,
                    'top_p' => $assistant->top_p,
                    'created_at' => $assistant->created_at->toJson(),
                    'updated_at' => $assistant->updated_at->toJson(),
                ],
            ],
        ]);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Updated Name',
        ]);
    }

    public function testCannotUpdateOthersAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Hacked',
        ]))
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);
    }

    public function testUpdateAssistantRecordsChangeInVersion(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        // Within the debounce window the change merges into the latest version.
        $versions = $assistant->fresh()->assistantVersions;
        self::assertCount(1, $versions);
        self::assertEquals(['name'], $versions->first()->changed_keys);
        self::assertSame('{"changes":["name"]}', $versions->first()->text);
    }

    public function testMultipleUpdatesWithinWindowMergeIntoOneVersion(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        // Two updates affecting different keys within the window merge their keys
        // into a single version entry.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'v2',
        ]))
            ->assertOk();

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'changed description',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();

        self::assertCount(1, $versions);
        self::assertEquals(['description', 'name'], $versions[0]->changed_keys);
        self::assertSame('{"changes":["description","name"]}', $versions[0]->text);
    }

    public function testChangeOutsideDebounceWindowCreatesNewVersion(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'first',
        ]))
            ->assertOk();

        self::assertCount(1, $assistant->fresh()->assistantVersions);

        // Advance past the debounce window (default 10s).
        $this->travel(11)->seconds();

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'second',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        self::assertCount(2, $versions);
        self::assertEquals(['name'], $versions[0]->changed_keys);
        self::assertEquals(['description'], $versions[1]->changed_keys);
    }

    public function testSlidingWindowExtendsOnEachChange(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        // Each merged change refreshes updated_at, sliding the window forward.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'a',
        ]))->assertOk();

        $this->travel(9)->seconds();
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'b',
        ]))->assertOk();

        $this->travel(9)->seconds();
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'greeting' => 'c',
        ]))->assertOk();

        // All three changes collapsed into the single (baseline) version via the sliding window.
        $versions = $assistant->fresh()->assistantVersions;
        self::assertCount(1, $versions);
        self::assertEquals(['description', 'greeting', 'name'], $versions->first()->changed_keys);
    }

    public function testRapidSameKeyUpdatesAreDebouncedIntoOneVersion(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'first',
        ]))
            ->assertOk();

        $initialVersionCount = $assistant->fresh()->assistantVersions()->count();

        // A second update affecting the same key within the debounce window is collapsed.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'second',
        ]))
            ->assertOk();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testCanUpdateAssistantWithTags(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $tagOne = AssistantTag::create(['text' => 'tag-one']);
        $tagTwo = AssistantTag::create(['text' => 'tag-two']);
        $assistant->assistantTags()->attach([$tagOne->id, $tagTwo->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_tags", $this->updatePayload($assistant, [
            'name' => $assistant->name,
        ]))
            ->assertOk();

        $assistant->refresh();
        self::assertEquals(2, $assistant->assistantTags()->count());
        self::assertTrue($assistant->assistantTags->pluck('text')->contains('tag-one'));
        self::assertTrue($assistant->assistantTags->pluck('text')->contains('tag-two'));
    }

    public function testUpdateResetsReviewToPending(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
            'reason' => 'Assistant updated since last review',
        ]);
    }

    public function testUpdateDoesNotCreateReviewWhenNoneExists(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseMissing('assistant_reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function testUpdateDoesNotIncrementVersionWhenDraft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Updated Name',
        ]);

        self::assertCount(1, $assistant->fresh()->assistantVersions);
    }

    public function testUpdateDoesNotResetReviewWhenDraft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);
    }

    private function updatePayload(Assistant $assistant, array $attributes, array $relationships = []): array
    {
        $data = [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => $attributes,
            ],
        ];
        $rels = $this->createRelationships($relationships);

        if ($rels) {
            $data['data']['relationships'] = $rels;
        }

        return $data;
    }
}
