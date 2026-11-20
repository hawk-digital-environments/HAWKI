<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\AgentToolRegistryTest\AgentToolRegistryTestFixtures;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Contracts\AgentTool;

/**
 * Minimal container-resolvable {@see AgentTool} fixture — no constructor
 * arguments, configurable defaults.
 */
final class RegistryFixtureAgentTool implements AgentTool
{
    public function key(): string
    {
        return 'fixture_capability';
    }

    public function isAvailable(Assistant $assistant, ?User $actor = null): bool
    {
        return true;
    }

    public function toolTransferStrings(Assistant $assistant, ?User $actor = null): array
    {
        return [];
    }

    public function usageInstructions(Assistant $assistant, ?User $actor = null): ?string
    {
        return null;
    }
}
