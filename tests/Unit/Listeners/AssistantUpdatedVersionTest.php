<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Services\Assistant\Listeners\AssistantUpdatedVersion;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\System\Time\CarbonClock;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

#[CoversClass(AssistantUpdatedVersion::class)]
class AssistantUpdatedVersionTest extends TestCase
{
    use RefreshDatabase;

    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        // Time is fully driven by the injected clock: both the "now" read and
        // the updated_at written by the listener come from it, so advancing the
        // MockClock moves the debounce window deterministically (same wiring as
        // the feature tests in AssistantUpdateTest).
        $this->clock = new MockClock();
        $this->app->instance(CarbonClockInterface::class, new CarbonClock($this->clock));
    }

    public function testMergesKeysIntoLatestWithinDebounceWindow(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['name']);
        $this->trigger($assistant, ['description']);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        self::assertCount(1, $versions);
        self::assertEquals(['description', 'name'], $versions->first()->changed_keys);
    }

    public function testCreatesNewVersionAfterDebounceWindow(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['name']);

        // Default debounce window is 10s (config/assistant.php).
        $this->clock->sleep(11);

        $this->trigger($assistant, ['description']);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        self::assertCount(2, $versions);
        self::assertSame('1.0', $versions[0]->version);
        self::assertEquals(['name'], $versions[0]->changed_keys);
        self::assertSame('2.0', $versions[1]->version);
        self::assertEquals(['description'], $versions[1]->changed_keys);
    }

    public function testSlidingWindowExtendsOnEachMerge(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        // Each merged change refreshes updated_at, sliding the window forward.
        $this->trigger($assistant, ['name']);
        $this->clock->sleep(9);
        $this->trigger($assistant, ['description']);
        $this->clock->sleep(9);
        $this->trigger($assistant, ['greeting']);

        $versions = $assistant->fresh()->assistantVersions;
        self::assertCount(1, $versions);
        self::assertEquals(['description', 'greeting', 'name'], $versions->first()->changed_keys);
    }

    public function testSortsAndDeduplicatesMergedKeys(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['b', 'a']);
        $this->trigger($assistant, ['a', 'c']);

        $version = $assistant->fresh()->assistantVersions()->latest('version')->first();
        self::assertEquals(['a', 'b', 'c'], $version->changed_keys);
    }

    public function testSkipsDraftAssistants(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $baselineCount = $assistant->assistantVersions()->count();

        $this->trigger($assistant, ['name']);

        // The listener returns before any DB write, so the factory baseline row
        // is untouched (changed_keys stays NULL).
        $versions = $assistant->fresh()->assistantVersions;
        self::assertCount($baselineCount, $versions);
        self::assertNull($versions->first()->changed_keys);
    }

    public function testInsertsAtVersionOneWhenNoVersionsExist(): void
    {
        // Bypass the factory's afterCreating baseline so the listener starts
        // from an empty version history (exercises the max('version') ?? 0.0
        // fallback in the create branch).
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->assistantVersions()->delete();

        $this->trigger($assistant, ['name']);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        self::assertCount(1, $versions);
        self::assertSame('1.0', $versions->first()->version);
        self::assertEquals(['name'], $versions->first()->changed_keys);
    }

    public function testEncodesTextAsChangesJson(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['foo']);

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertSame('{"changes":["foo"]}', $version->text);
    }

    private function trigger(Assistant $assistant, array $changedKeys): void
    {
        app(AssistantUpdatedVersion::class)->handle(new AssistantUpdatedEvent($assistant, $changedKeys));
    }
}
