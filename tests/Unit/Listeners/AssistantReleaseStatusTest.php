<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\AssistantTriggerReleaseStatus;
use App\Listeners\AssistantReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantReleaseStatusTest extends TestCase
{
    use RefreshDatabase;

    private function trigger(Assistant $assistant, ReleaseStage $old, ReleaseStage $new): void
    {
        app(AssistantReleaseStatus::class)->handle(
            new AssistantTriggerReleaseStatus($assistant, $old, $new),
        );
    }

    public function test_does_not_create_review_when_target_stage_is_draft(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, ReleaseStage::ORGANIZATIONAL, ReleaseStage::DRAFT);

        $this->assertDatabaseMissing('reviews', ['assistant_id' => $assistant->id]);
    }

    public function test_does_not_create_review_when_target_stage_is_private(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, ReleaseStage::ORGANIZATIONAL, ReleaseStage::PRIVATE);

        $this->assertDatabaseMissing('reviews', ['assistant_id' => $assistant->id]);
    }

    public function test_creates_pending_review_when_target_stage_is_organizational(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, ReleaseStage::PRIVATE, ReleaseStage::ORGANIZATIONAL);

        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);
    }

    public function test_creates_pending_review_when_target_stage_is_federated(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, ReleaseStage::ORGANIZATIONAL, ReleaseStage::FEDERATED);

        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);
    }
}
