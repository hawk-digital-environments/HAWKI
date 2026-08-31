<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Listeners\AssistantReleaseStatus;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantReleaseStatus::class)]
class AssistantReleaseStatusTest extends TestCase
{
    use RefreshDatabase;

    public function testDoesNotCreateReviewWhenTargetStageIsDraft(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::DRAFT);

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testDoesNotCreateReviewWhenTargetStageIsPrivate(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::PRIVATE);

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testDeletesNonDeniedReviewWhenTargetStageIsPrivate(): void
    {
        $assistant = Assistant::factory()->create();
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::PRIVATE);

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testDeletesNonDeniedReviewWhenTargetStageIsDraft(): void
    {
        $assistant = Assistant::factory()->create();
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::DRAFT);

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testKeepsDeniedReviewWhenTargetStageIsPrivate(): void
    {
        $assistant = Assistant::factory()->create();
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::PRIVATE);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);
    }

    public function testDoesNotCreateReviewWhenTargetStageIsOrganizational(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, AssistantReleaseStage::PRIVATE, AssistantReleaseStage::ORGANIZATIONAL);

        // Public-stage transitions are approval-driven; the listener must not
        // create or clobber the review (pending requests are opened by the
        // AssistantService, promotions only happen once a review is approved).
        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testDoesNotCreateReviewWhenTargetStageIsFederated(): void
    {
        $assistant = Assistant::factory()->create();

        $this->trigger($assistant, AssistantReleaseStage::ORGANIZATIONAL, AssistantReleaseStage::FEDERATED);

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testKeepsApprovedReviewWhenPromotedToPublicStage(): void
    {
        $assistant = Assistant::factory()->create();
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->trigger($assistant, AssistantReleaseStage::PRIVATE, AssistantReleaseStage::ORGANIZATIONAL);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);
    }

    private function trigger(Assistant $assistant, AssistantReleaseStage $old, AssistantReleaseStage $new): void
    {
        app(AssistantReleaseStatus::class)->handle(new AssistantReleaseStageChangedEvent($assistant, $old, $new));
    }
}
