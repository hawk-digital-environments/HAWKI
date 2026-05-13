<?php

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Listeners\AssistantReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_release_assistant(): void
    {
        Event::fake(AssistantTriggerReleaseStatus::class);
        Event::assertListening(AssistantTriggerReleaseStatus::class, AssistantReleaseStatus::class);

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);
        Event::fake(AssistantTriggerReleaseStatus::class);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/-actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ]);

        $response->assertOk();

        $assistant->refresh();
        $this->assertEquals(ReleaseStage::ORGANIZATIONAL->value, $assistant->release_stage);

        Event::assertDispatched(AssistantTriggerReleaseStatus::class, function ($event) {
            return $event->oldStage === ReleaseStage::PRIVATE
                && $event->newStage === ReleaseStage::ORGANIZATIONAL;
        });
    }

    public function test_cannot_release_others_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/-actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);
    }

    public function test_guest_cannot_release_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/-actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertUnauthorized();
    }

    public function test_release_with_same_stage_does_not_dispatch_event(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);
        Event::fake(AssistantTriggerReleaseStatus::class);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/-actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        Event::assertNotDispatched(AssistantTriggerReleaseStatus::class);
    }

    public function test_release_with_invalid_stage_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/-actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => 'invalid',
                ],
            ],
        ])
            ->assertUnprocessable();
    }
}
