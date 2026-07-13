<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function testCanReleaseAssistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        Event::fake(AssistantTriggerReleaseStatus::class);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertOk();

        $assistant->refresh();
        self::assertEquals(AssistantReleaseStage::ORGANIZATIONAL->value, $assistant->release_stage);

        Event::assertDispatched(AssistantTriggerReleaseStatus::class, static function (AssistantTriggerReleaseStatus $event): bool {
            return $event->oldStage === AssistantReleaseStage::PRIVATE
                && $event->newStage === AssistantReleaseStage::ORGANIZATIONAL;
        });
    }

    public function testCannotReleaseOthersAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function testGuestCannotReleaseAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function testReleaseWithSameStageDoesNotDispatchEvent(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        Event::fake(AssistantTriggerReleaseStatus::class);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        Event::assertNotDispatched(AssistantTriggerReleaseStatus::class);
    }

    public function testReleaseWithInvalidStageReturnsValidationError(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => 'invalid',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testCannotPublishAssistantWithDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testCannotFederateAssistantWithDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::FEDERATED->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testCanPublishAfterAdminClearsDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $review = AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->actingAsUser($user);

        // Admin manually clears the denial.
        $review->status = AssistantReviewStatus::PENDING->value;
        $review->reason = null;
        $review->save();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertOk();

        // Releasing to a public stage resets the review to pending.
        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }
}
