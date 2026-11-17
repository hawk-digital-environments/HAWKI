<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Services\Assistant\Listeners\AssistantUpdatedVersion;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Edits accumulate their changed keys on the assistant's working-draft row
 * (version 0.0) — the delta a reviewer sees between the published state and
 * the pending draft. They never spawn additional version rows; the previous
 * debounce window is obsolete because there is only ever one mutable row.
 */
#[CoversClass(AssistantUpdatedVersion::class)]
class AssistantUpdatedVersionTest extends TestCase
{
    use RefreshDatabase;

    public function testItMergesKeysIntoTheDraftRow(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['name']);
        $this->trigger($assistant, ['description']);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions->first()->version);
        static::assertEquals(['description', 'name'], $versions->first()->changed_keys);
    }

    public function testItNeverCreatesNewVersionsForRepeatedEdits(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['name']);
        $this->trigger($assistant, ['description']);
        $this->trigger($assistant, ['greeting']);

        $versions = $assistant->fresh()->assistantVersions;
        static::assertCount(1, $versions);
        static::assertEquals(['description', 'greeting', 'name'], $versions->first()->changed_keys);
    }

    public function testItSortsAndDeduplicatesMergedKeys(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['b', 'a']);
        $this->trigger($assistant, ['a', 'c']);

        $version = $assistant->fresh()->assistantVersions()->latest('version')->first();
        static::assertEquals(['a', 'b', 'c'], $version->changed_keys);
    }

    public function testTracksDraftStageAssistants(): void
    {
        // Unlike the previous journal implementation, draft-stage edits are
        // tracked too: the draft row's changed keys describe everything not
        // yet published, no matter which stage the assistant currently sits in.
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);

        $this->trigger($assistant, ['name']);

        $versions = $assistant->fresh()->assistantVersions;
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions->first()->version);
        static::assertEquals(['name'], $versions->first()->changed_keys);
    }

    public function testItInsertsADraftRowWhenNoVersionsExist(): void
    {
        // Bypass the factory's afterCreating baseline so the listener starts
        // from an empty version history (covers assistants whose rows predate
        // the draft model).
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $assistant->assistantVersions()->delete();

        $this->trigger($assistant, ['name']);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions->first()->version);
        static::assertEquals(['name'], $versions->first()->changed_keys);
    }

    public function testItLeavesTheTextEmptyOnEdits(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->trigger($assistant, ['foo']);

        $version = $assistant->fresh()->assistantVersions()->latest('version')->first();
        // `text` carries only the creator's release note (written by the
        // release action); content edits leave it empty.
        static::assertSame('', $version->text);
    }

    public function testItKeepsTheExistingReleaseNoteOnMerge(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        // Simulate the release action having recorded a note on the draft row.
        $assistant->assistantVersions()
            ->where('version', AssistantVersion::DRAFT_VERSION)
            ->first()
            ->forceFill([
                'text' => 'Tightened the system prompt',
            ])->save();

        $this->trigger($assistant, ['name']);

        $version = $assistant->fresh()->assistantVersions()->latest('version')->first();
        static::assertSame('Tightened the system prompt', $version->text);
        static::assertEquals(['name'], $version->changed_keys);
    }

    private function trigger(Assistant $assistant, array $changedKeys): void
    {
        app(AssistantUpdatedVersion::class)->handle(new AssistantUpdatedEvent($assistant, $changedKeys));
    }
}
