<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi\Events;

use App\Events\Traits\DispatchableFilter;
use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Laravel\Ai\Contracts\Tool;

/**
 * Filter event dispatched after a HAWKI tool has been resolved and converted for a capability key,
 * before it is returned to the caller.
 *
 * This event fires in {@see \App\Services\Ai\Tools\LaravelAi\LaravelToolResolver::resolveToolForCapability()},
 * which looks up an active {@see AiTool} database record linked to the given capability and converts
 * it into a {@see Tool} for use by the agent. The event exposes both the raw HAWKI tool record
 * and the converted Laravel AI tool.
 *
 * Listeners may replace the tool via {@see setTool()} to:
 * - Wrap the converted tool with additional configuration or parameter constraints.
 * - Apply model- or user-specific overrides to the tool before it reaches the agent.
 * - Substitute an entirely different tool implementation for the same capability.
 *
 * Read-only: {@see getCapabilityKey()}, {@see getContext()}, {@see getHawkiTool()}, {@see getToolSettings()}
 * Writable:  {@see getTool()} / {@see setTool()}
 */
class ToolForCapabilityResolvedFilterEvent
{
    use DispatchableFilter;

    private Tool $tool;

    public function __construct(
        Tool                                $tool,
        /** The capability key that was used to look up the tool (e.g. "web_search"). */
        private readonly string             $capabilityKey,
        /** The request context containing the resolved model and provider. */
        private readonly AgentRequestContext $context,
        /** The HAWKI database record for the tool that was matched by capability. */
        private readonly AiTool             $hawkiTool,
        /** Additional settings passed by the caller when requesting this tool. */
        private readonly array              $toolSettings,
    )
    {
        $this->tool = $tool;
    }

    /**
     * Returns the currently resolved tool.
     * This may already have been replaced by an earlier listener in the same dispatch.
     */
    public function getTool(): Tool
    {
        return $this->tool;
    }

    /**
     * Replace the resolved tool that will be returned to the agent.
     */
    public function setTool(Tool $tool): void
    {
        $this->tool = $tool;
    }

    /** The capability key that was used to resolve the tool (e.g. "web_search"). */
    public function getCapabilityKey(): string
    {
        return $this->capabilityKey;
    }

    /** The request context containing the resolved model, provider, and parameters. */
    public function getContext(): AgentRequestContext
    {
        return $this->context;
    }

    /** The HAWKI tool database record that was matched by capability and converted into {@see getTool()}. */
    public function getHawkiTool(): AiTool
    {
        return $this->hawkiTool;
    }

    /**
     * The additional settings that were passed by the caller when requesting this tool.
     *
     * @return array<string, mixed>
     */
    public function getToolSettings(): array
    {
        return $this->toolSettings;
    }
}
