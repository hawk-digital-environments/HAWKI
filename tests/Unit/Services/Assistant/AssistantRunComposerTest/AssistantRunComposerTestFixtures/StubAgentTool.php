<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\AssistantRunComposerTest\AssistantRunComposerTestFixtures;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\Contracts\AgentTool;

/**
 * Mutable test double: configure availability, transfer strings, and usage
 * instructions per test.
 */
final class StubAgentTool implements AgentTool
{
    public bool $available = false;

    /** @var list<string> */
    public array $transferStrings = [];

    public string|null $usageInstructions = null;

    public function __construct(
        private readonly string $key = WellKnownCapabilities::KNOWLEDGE_BASE,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function isAvailable(Assistant $assistant, ?User $actor = null): bool
    {
        return $this->available;
    }

    public function toolTransferStrings(Assistant $assistant, ?User $actor = null): array
    {
        return $this->transferStrings;
    }

    public function usageInstructions(Assistant $assistant, ?User $actor = null): ?string
    {
        return $this->usageInstructions;
    }
}
