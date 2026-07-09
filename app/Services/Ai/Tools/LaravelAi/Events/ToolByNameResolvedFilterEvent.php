<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Laravel\Ai\Contracts\Tool;

/**
 * Filter event dispatched after a HAWKI tool has been resolved and converted by name,
 * before it is returned to the caller.
 *
 * This event fires in {@see \App\Services\Ai\Tools\LaravelAi\LaravelToolResolver::resolveToolByName()},
 * which looks up an active {@see \App\Models\Ai\AiTool} database record by its exact name and
 * converts it into a {@see Tool} for use by the agent.
 *
 * Listeners may replace the tool via {@see setTool()} to:
 * - Wrap the converted tool with additional configuration or parameter constraints.
 * - Apply model- or user-specific overrides to the tool before it reaches the agent.
 * - Substitute a different tool implementation while keeping the same name lookup.
 *
 * Read-only: {@see getToolName()}, {@see getContext()}, {@see getToolSettings()}
 * Writable:  {@see getTool()} / {@see setTool()}
 */
class ToolByNameResolvedFilterEvent
{
    use DispatchableFilter;

    private Tool $tool;

    public function __construct(
        Tool                                $tool,
        /** The exact tool name that was looked up (matches ai_tools.name). */
        private readonly string             $toolName,
        /** The request context containing the resolved model and provider. */
        private readonly AgentRequestContext $context,
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

    /** The exact tool name (ai_tools.name) that was used to resolve this tool. */
    public function getToolName(): string
    {
        return $this->toolName;
    }

    /** The request context containing the resolved model, provider, and parameters. */
    public function getContext(): AgentRequestContext
    {
        return $this->context;
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
