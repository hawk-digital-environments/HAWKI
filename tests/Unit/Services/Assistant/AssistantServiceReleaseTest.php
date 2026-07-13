<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Values\AssistantReleaseStage;
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

    public function testRevokeReleaseDemotesToPrivate(): void
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
}
