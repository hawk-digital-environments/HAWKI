<?php

declare(strict_types=1);

namespace App\Services\Assistant\Contracts;

use App\Models\Assistants\Assistant;
use App\Models\User;

/**
 * An ambient capability a module grants to an assistant's agent run.
 *
 * Where attached tools (`ai_tools`) are explicitly chosen by the assistant
 * creator, an agent tool is granted automatically whenever the module
 * serving it can deliver it — the assistant simply "has" the capability,
 * the same way the file converter enriches uploads in the background
 * without any per-assistant wiring.
 *
 * Implementations live in the serving module (the Rag slice provides the
 * knowledge_base agent tool) and are declared in
 * {@see \App\Services\Assistant\AgentToolRegistry} from the module's
 * service provider. An active tool supersedes the assistant's explicit
 * selections for its capability key: attached tools and persisted
 * capability strings for that key are dropped from the run and the
 * tool's own transfer strings are injected instead.
 */
interface AgentTool
{
    /**
     * The well-known capability key this tool serves — a
     * {@see \App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities} constant.
     */
    public function key(): string;

    /**
     * Whether the module can serve this capability for the given run. An
     * unavailable tool contributes nothing and leaves the assistant's
     * explicit selections untouched.
     */
    public function isAvailable(Assistant $assistant, ?User $actor = null): bool;

    /**
     * The tool-transfer strings injected into the run when available
     * (format per {@see \App\Services\Ai\Agents\Implementations\Chat\Values\ToolTransferData}).
     *
     * @return list<string>
     */
    public function toolTransferStrings(Assistant $assistant, ?User $actor = null): array;

    /**
     * A rendered system-prompt module (house style per
     * {@see \App\Services\Assistant\Values\AssistantPromptTemplate}) appended
     * to the assistant's system prompt while this tool is active — typically
     * the usage rules for the capability this tool grants. Null contributes
     * nothing.
     */
    public function usageInstructions(Assistant $assistant, ?User $actor = null): ?string;
}
