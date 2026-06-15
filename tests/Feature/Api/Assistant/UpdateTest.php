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

    public function test_update_assistant_increments_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        $version = $assistant->fresh()->versions()->where('version', 2.0)->first();
        $this->assertEquals(['name'], $version->changed_keys);
    }

    public function test_update_assistant_with_version_text(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
            'version_text' => 'Changed the name',
        ]))
            ->assertOk();

        $version = $assistant->fresh()->versions()->where('version', 2.0)->first();
        $this->assertEquals('Changed the name', $version->text);
        $this->assertEquals(['name'], $version->changed_keys);
    }

    public function test_multiple_updates_increment_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'v2',
        ]))
            ->assertOk();

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'v3',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->versions()->orderBy('version')->get();

        $this->assertCount(3, $versions);

        $this->assertEquals(1.0, (float) $versions[0]->version);
        $this->assertNull($versions[0]->changed_keys);

        $this->assertEquals(2.0, (float) $versions[1]->version);
        $this->assertEquals(['name'], $versions[1]->changed_keys);

        $this->assertEquals(3.0, (float) $versions[2]->version);
        $this->assertEquals(['name'], $versions[2]->changed_keys);
    }

    public function test_can_update_assistant_with_tags(): void
    {
        $tag1 = Tag::create(['text' => 'tag-one']);
        $tag2 = Tag::create(['text' => 'tag-two']);

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $assistant->tags()->attach([$tag1->id, $tag2->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('patch', "/api/assistants/{$assistant->id}?include=tags", $this->updatePayload($assistant, [
            'name' => $assistant->name,
        ], [
            'tags' => [$tag2->id],
        ]))
            ->assertOk();

        $this->assertDatabaseMissing('assistant_tag', [
            'assistant_id' => $assistant->id,
            'tag_id' => $tag1->id,
        ]);

        $assistant->refresh();
        $this->assertEquals(1, $assistant->tags()->count());
        $this->assertTrue($assistant->tags->pluck('text')->contains('tag-two'));

        $version = $assistant->fresh()->versions()->where('version', 2.0)->first();
        $this->assertEquals(['tags'], $version->changed_keys);
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
            'reason' => null,
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
