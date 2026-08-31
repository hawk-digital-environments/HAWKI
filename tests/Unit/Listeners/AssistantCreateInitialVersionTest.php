<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use App\Services\Assistant\Listeners\AssistantCreateInitialVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantCreateInitialVersion::class)]
class AssistantCreateInitialVersionTest extends TestCase
{
    use RefreshDatabase;

    public function testCreatesBaselineVersionAtOne(): void
    {
        $assistant = $this->createAssistantWithoutVersions();

        $this->trigger($assistant);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        self::assertCount(1, $versions);
        self::assertSame('1.0', $versions->first()->version);
        self::assertSame('{"changes":[]}', $versions->first()->text);
    }

    public function testDoesNotClobberExistingVersions(): void
    {
        $assistant = Assistant::factory()->create();
        $existingCount = $assistant->assistantVersions()->count();

        $this->trigger($assistant);

        self::assertSame($existingCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testIsIdempotent(): void
    {
        $assistant = $this->createAssistantWithoutVersions();

        $this->trigger($assistant);
        $this->trigger($assistant);

        self::assertCount(1, $assistant->fresh()->assistantVersions);
    }

    private function trigger(Assistant $assistant): void
    {
        app(AssistantCreateInitialVersion::class)->handle(new AssistantCreatedEvent($assistant));
    }

    /**
     * The factory's afterCreating hook seeds a baseline version. Deleting it
     * lets these tests exercise the listener from a truly empty state.
     */
    private function createAssistantWithoutVersions(): Assistant
    {
        $assistant = Assistant::factory()->create();
        $assistant->assistantVersions()->delete();

        return $assistant->fresh();
    }
}
