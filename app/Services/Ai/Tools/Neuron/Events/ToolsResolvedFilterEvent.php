<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Neuron\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Tools\ProviderToolInterface;

/**
 * Filter event dispatched after the full list of tools has been resolved for a request,
 * but before it is returned to the caller.
 *
 * Listeners may inspect the resolved tools together with the originating request context
 * and replace the list via {@see setTools()} to add, remove, or reorder entries.
 *
 * This makes it possible for plugins to:
 * - Remove tools that are not allowed for a specific model or user context.
 * - Inject additional tools that are determined at runtime.
 * - Reorder tools to influence which one the AI agent prefers.
 *
 * Read-only properties ($parameterSource, $requestedCapabilities, $requestedTools) expose
 * the original request context but cannot be replaced. Only $tools is writable.
 */
class ToolsResolvedFilterEvent
{
    use DispatchableFilter;

    /**
     * The resolved list of provider tools.
     * Replace via {@see setTools()} to filter or augment the list.
     *
     * @var array<int, ProviderToolInterface>
     */
    private array $tools;

    /**
     * @param array<int, ProviderToolInterface> $tools The fully resolved list of tools.
     * @param ParameterSource $parameterSource The parameter source for the current request, containing the model and other contextual information.
     * @param array<string> $requestedCapabilities The capability names that were requested for this message.
     * @param array<string> $requestedTools The tool names that were explicitly requested for this message.
     */
    public function __construct(
        array                            $tools,
        /** The parameter source for the current request, containing the model and provider. */
        private readonly ParameterSource $parameterSource,
        /** The capability names that were requested for this message. */
        private readonly array           $requestedCapabilities,
        /** The tool names that were explicitly requested for this message. */
        private readonly array           $requestedTools,
    )
    {
        $this->tools = $tools;
    }

    /**
     * Returns the current list of resolved provider tools.
     * This list may already have been modified by an earlier listener.
     *
     * @return array<int, ProviderToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Replace the list of provider tools that will be returned to the caller.
     *
     * @param array<int, ProviderToolInterface> $tools
     */
    public function setTools(array $tools): void
    {
        $this->tools = $tools;
    }

    /**
     * The parameter source for the current request.
     * Contains the resolved model, provider, and other contextual data.
     */
    public function getParameterSource(): ParameterSource
    {
        return $this->parameterSource;
    }

    /**
     * The capability names that were requested for this message (e.g. "web_search", "code_execution").
     *
     * @return array<string>
     */
    public function getRequestedCapabilities(): array
    {
        return $this->requestedCapabilities;
    }

    /**
     * The tool names (ai_tools.name) that were explicitly requested for this message.
     *
     * @return array<string>
     */
    public function getRequestedTools(): array
    {
        return $this->requestedTools;
    }
}
