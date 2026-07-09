<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Laravel\Ai\Providers\Tools\ProviderTool;

/**
 * Filter event dispatched after a native provider tool has been resolved for a capability key,
 * before it is returned to the caller.
 *
 * Native tools are provider-specific implementations (e.g. built-in web search for OpenAI)
 * that are created directly by the provider adapter via a factory callable. This event allows
 * listeners to inspect or replace the resolved {@see ProviderTool} before it reaches the agent.
 *
 * Listeners may replace the tool via {@see setTool()} to:
 * - Wrap the resolved tool with additional configuration or restrictions.
 * - Substitute a different provider-native tool for the same capability.
 * - Enforce policy constraints on which tool variant is used.
 *
 * Read-only: {@see getCapabilityKey()}, {@see getContext()}, {@see getToolSettings()}
 * Writable:  {@see getTool()} / {@see setTool()}
 */
class NativeToolResolvedFilterEvent
{
    use DispatchableFilter;

    private ProviderTool $tool;

    public function __construct(
        ProviderTool                        $tool,
        /** The capability key that was used to look up the native tool (e.g. "web_search"). */
        private readonly string             $capabilityKey,
        /** The request context containing the resolved model and provider. */
        private readonly AgentRequestContext $context,
        /** Additional settings passed by the caller when requesting this tool. */
        private readonly array              $toolSettings,
    )
    {
        $this->tool = $tool;
    }

    /**
     * Returns the currently resolved native provider tool.
     * This may already have been replaced by an earlier listener in the same dispatch.
     */
    public function getTool(): ProviderTool
    {
        return $this->tool;
    }

    /**
     * Replace the resolved native provider tool that will be returned to the agent.
     */
    public function setTool(ProviderTool $tool): void
    {
        $this->tool = $tool;
    }

    /** The capability key that was used to resolve the native tool (e.g. "web_search"). */
    public function getCapabilityKey(): string
    {
        return $this->capabilityKey;
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
