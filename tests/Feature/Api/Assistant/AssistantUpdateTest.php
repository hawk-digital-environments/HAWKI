<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use App\Services\Assistant\Listeners\AssistantUpdatedVersion;
use App\Services\Assistant\Listeners\UnpublishReleasedAssistantOnEdit;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use App\Services\System\Time\CarbonClock;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Clock\MockClock;
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
        Event::fake(AssistantUpdatedEvent::class);
        Event::assertListening(AssistantUpdatedEvent::class, AssistantUpdatedVersion::class);
        Event::assertListening(AssistantUpdatedEvent::class, UnpublishReleasedAssistantOnEdit::class);

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
                    'release_stage' => $assistant->release_stage->value,
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
        static::assertCount(1, $versions);
        static::assertEquals(['name'], $versions->first()->changed_keys);
        static::assertSame('', $versions->first()->text);
    }

    public function testItMergesMultipleUpdatesWithinTheWindowIntoOneVersion(): void
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

        static::assertCount(1, $versions);
        static::assertEquals(['description', 'name'], $versions[0]->changed_keys);
        static::assertSame('', $versions[0]->text);
    }

    public function testItAccumulatesChangesOnTheDraftRowRegardlessOfTiming(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        // Drive the app clock so advancing past the old debounce window is
        // provable — the draft row must remain the single accumulator.
        $clock = new MockClock();
        $this->app->instance(CarbonClockInterface::class, new CarbonClock($clock));

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'first',
        ]))
            ->assertOk();

        // Advance past the debounce window the previous journal-style
        // implementation used (default 10s) — no new version row appears.
        $clock->sleep(11);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'second',
        ]))
            ->assertOk();

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions[0]->version);
        static::assertEquals(['description', 'name'], $versions[0]->changed_keys);
    }

    public function testItExtendsTheWindowOnEachChange(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        // Drive the listener's clock from a controllable MockClock so the
        // debounce window can be advanced deterministically. The listener reads
        // and writes time solely through this injected clock, so advancing the
        // MockClock moves both "now" and the version's updated_at in lockstep.
        $clock = new MockClock();
        $this->app->instance(CarbonClockInterface::class, new CarbonClock($clock));

        // Each merged change refreshes updated_at, sliding the window forward.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'a',
        ]))->assertOk();

        $clock->sleep(9);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'description' => 'b',
        ]))->assertOk();

        $clock->sleep(9);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'greeting' => 'c',
        ]))->assertOk();

        // All three changes collapsed into the single (baseline) version via the sliding window.
        $versions = $assistant->fresh()->assistantVersions;
        static::assertCount(1, $versions);
        static::assertEquals(['description', 'greeting', 'name'], $versions->first()->changed_keys);
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

        static::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
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
        static::assertEquals(2, $assistant->assistantTags()->count());
        static::assertTrue($assistant->assistantTags->pluck('text')->contains('tag-one'));
        static::assertTrue($assistant->assistantTags->pluck('text')->contains('tag-two'));
    }

    public function testItUnpublishesAReleasedAssistantOnUpdate(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'name' => 'Updated Name',
        ]))
            ->assertOk();

        // The first content edit demotes the released assistant to private:
        // the change never reaches users, and the creator must resubmit for
        // review before it is published again.
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->fresh()->release_stage);
        static::assertNull($assistant->fresh()->requested_release_stage);

        // The stage-change cascade tears down the non-denied review.
        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
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

        static::assertCount(1, $assistant->fresh()->assistantVersions);
    }

    public function testUpdateDoesNotResetReviewWhenDraft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        AssistantReview::forceCreate([
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

    public function testCanUpdateAssistantHandleWithoutLeadingAt(): void
    {
        // Regression: the update branch previously required a leading "@" in the
        // handle regex, so valid handles without it were rejected on PATCH even
        // though create accepted them. The create and update regexes must agree.
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'handle' => 'old-handle',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'handle' => 'new-handle',
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'handle' => 'new-handle',
        ]);
    }

    public function testUpdateRejectsHandleWithLeadingAt(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'handle' => 'old-handle',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'handle' => '@invalid',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/handle');
    }

    public function testUpdateRejectsInvalidHandleFormat(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'handle' => 'old-handle',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'handle' => 'in valid',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/handle');
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
