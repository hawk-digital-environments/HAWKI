<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantService::class)]
class AssistantServiceReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function testPromoteRequestedPromotesToRequestedStage(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->promoteRequested($assistant);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
            'requested_release_stage' => null,
        ]);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testPromoteRequestedNoopWhenNoRequestedStage(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => null,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->promoteRequested($assistant);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => null,
        ]);

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testPromoteRequestedNoopForNonPublicRequestedStage(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->promoteRequested($assistant);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testItDemotesToPrivateOnRevokeRelease(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
            'requested_release_stage' => AssistantReleaseStage::FEDERATED->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->revokeRelease($assistant);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => null,
        ]);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testReleaseRecordsNoteOnDraftVersion(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->release($assistant, AssistantReleaseStage::PRIVATE, 'Fixed the greeting');

        // Every submit freezes the state onto the working-draft row; the note
        // travels with it.
        $version = $assistant->assistantVersions()->latest('version')->first();
        static::assertSame('Fixed the greeting', $version->text);
        static::assertSame('0.0', $version->version);
    }

    public function testReleaseRecordsNoteWhileReviewIsPending(): void
    {
        // Private -> organizational without an approval: the stage stays, the
        // review opens as pending — and the note is still recorded.
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->release($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'Ready for the org');

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $version = $assistant->assistantVersions()->latest('version')->first();
        static::assertSame('Ready for the org', $version->text);
    }

    public function testItLeavesTheVersionTextUntouchedWhenTheReleaseCarriesNoNote(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->release($assistant, AssistantReleaseStage::PRIVATE);

        $version = $assistant->assistantVersions()->latest('version')->first();
        static::assertSame('', $version->text);
    }

    public function testReleaseReopensNeedsRevisionReviewAsPending(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $assistant->assistantReview()->create([
            'status' => AssistantReviewStatus::NEEDS_REVISION->value,
            'reason' => 'Tighten the prompt',
        ]);

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        // A needs_revision review does not block resubmission (only DENIED
        // does): the review reopens as pending, starting a fresh round with
        // the previous denial reason cleared.
        app(AssistantService::class)->release($assistant, AssistantReleaseStage::ORGANIZATIONAL);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
            'reason' => null,
        ]);
    }

    public function testReleaseNoteDoesNotOverwriteWhenReviewDenied(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $assistant->assistantReview()->create([
            'status' => AssistantReviewStatus::DENIED->value,
        ]);
        $assistant->assistantVersions()->latest('version')->first()->forceFill([
            'text' => 'Previous note',
        ])->save();

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->release($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'Try again');

        $version = $assistant->assistantVersions()->latest('version')->first();
        static::assertSame('Previous note', $version->text);
    }

    public function testReleaseNoteCreatesDraftVersionWhenNoneExists(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $assistant->assistantVersions()->delete();

        Event::fake([AssistantReleaseStageChangedEvent::class]);

        app(AssistantService::class)->release($assistant, AssistantReleaseStage::PRIVATE, 'First publish');

        $versions = $assistant->assistantVersions()->orderBy('version')->get();
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions->first()->version);
        static::assertSame('First publish', $versions->first()->text);
    }
}
