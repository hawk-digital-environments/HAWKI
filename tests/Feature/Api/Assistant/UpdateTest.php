<?php

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantUpdated;
use App\Listeners\AssistantUpdatedVersion;
use App\Listeners\ResetReviewOnUpdate;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\Review;
use App\Models\Assistants\Tag;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use AssistantFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
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

    public function test_can_update_assistant(): void
    {
        Event::fake(AssistantUpdated::class);
        Event::assertListening(AssistantUpdated::class, AssistantUpdatedVersion::class);
        Event::assertListening(AssistantUpdated::class, ResetReviewOnUpdate::class);

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id, 'remixed_creator_id' => null]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
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

    public function test_cannot_update_others_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Hacked',
        ]))
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);
    }

    public function test_update_assistant_records_change_in_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        // Within the debounce window the change merges into the latest version.
        $versions = $assistant->fresh()->versions;
        $this->assertCount(1, $versions);
        $this->assertEquals(['name'], $versions->first()->changed_keys);
        $this->assertSame('{"changes":["name"]}', $versions->first()->text);
    }

    public function test_multiple_updates_within_window_merge_into_one_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        // Two updates affecting different keys within the window merge their keys
        // into a single version entry.
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'v2',
        ]))
            ->assertOk();

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'changed description',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->versions()->orderBy('version')->get();

        $this->assertCount(1, $versions);
        $this->assertEquals(['description', 'name'], $versions[0]->changed_keys);
        $this->assertSame('{"changes":["description","name"]}', $versions[0]->text);
    }

    public function test_change_outside_debounce_window_creates_new_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'first',
        ]))
            ->assertOk();

        $this->assertCount(1, $assistant->fresh()->versions);

        // Advance past the debounce window (default 10s).
        $this->travel(11)->seconds();

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'second',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->versions()->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertEquals(['name'], $versions[0]->changed_keys);
        $this->assertEquals(['description'], $versions[1]->changed_keys);
    }

    public function test_sliding_window_extends_on_each_change(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        // Each merged change refreshes updated_at, sliding the window forward.
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'a',
        ]))->assertOk();

        $this->travel(9)->seconds();
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'b',
        ]))->assertOk();

        $this->travel(9)->seconds();
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'greeting' => 'c',
        ]))->assertOk();

        // All three changes collapsed into the single (baseline) version via the sliding window.
        $versions = $assistant->fresh()->versions;
        $this->assertCount(1, $versions);
        $this->assertEquals(['description', 'greeting', 'name'], $versions->first()->changed_keys);
    }

    public function test_rapid_same_key_updates_are_debounced_into_one_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'first',
        ]))
            ->assertOk();

        $initialVersionCount = $assistant->fresh()->versions()->count();

        // A second update affecting the same key within the debounce window is collapsed.
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'second',
        ]))
            ->assertOk();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
    }

    public function test_can_update_assistant_with_tags(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $tagOne = Tag::create(['text' => 'tag-one']);
        $tagTwo = Tag::create(['text' => 'tag-two']);
        $assistant->tags()->attach([$tagOne->id, $tagTwo->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('patch', "/api/assistants/{$assistant->id}?include=assistant_tags", $this->updatePayload($assistant, [
            'name' => $assistant->name,
        ]))
            ->assertOk();

        $assistant->refresh();
        $this->assertEquals(2, $assistant->tags()->count());
        $this->assertTrue($assistant->tags->pluck('text')->contains('tag-one'));
        $this->assertTrue($assistant->tags->pluck('text')->contains('tag-two'));
    }

    public function test_update_resets_review_to_pending(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
            'reason' => 'Assistant updated since last review',
        ]);
    }

    public function test_update_does_not_create_review_when_none_exists(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseMissing('reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function test_update_does_not_increment_version_when_draft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::DRAFT->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Updated Name',
        ]);

        $this->assertCount(1, $assistant->fresh()->versions);
    }

    public function test_update_does_not_reset_review_when_draft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::DRAFT->value,
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);
    }
}
