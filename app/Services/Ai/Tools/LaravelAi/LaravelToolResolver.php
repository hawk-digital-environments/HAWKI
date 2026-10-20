<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Tools\Exceptions\ToolNotFoundException;
use App\Services\Ai\Tools\LaravelAi\Events\NativeToolResolvedFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\ToolByNameResolvedFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\ToolForCapabilityResolvedFilterEvent;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;

/**
 * Resolves the correct {@see Tool} instance for a given capability or name within an agent request.
 *
 * Three resolution strategies are provided:
 *
 * 1. **Native provider tool** (`resolveNativeToolForCapability`) — asks the AI provider adapter
 *    for a built-in implementation of the capability (e.g. a provider-side web-search tool).
 *    Throws {@see ToolNotFoundException} when the active provider does not support the capability.
 *
 * 2. **HAWKI tool by capability** (`resolveToolForCapability`) — finds the first active,
 *    online {@see AiTool} on the current model whose effective capability matches the key,
 *    then converts it via {@see LaravelToolConverter}. Throws when no matching tool is found.
 *
 * 3. **HAWKI tool by name** (`resolveToolByName`) — finds a tool by its exact `name` field
 *    on the current model and converts it. Throws when the name is not present.
 *
 * Each strategy fires a filter event after resolution, giving listeners the opportunity to
 * swap or decorate the resolved tool before it is handed to the agent.
 */
#[Singleton]
class LaravelToolResolver
{
    public function __construct(
        private readonly LaravelToolConverter $toolConverter
    )
    {
    }

    /**
     * Resolves a native provider-side tool for the given capability key.
     *
     * Delegates to the provider adapter's `getNativeToolFactoryForCapability()` method.
     * Use this when you want the provider's own implementation (e.g. OpenAI's built-in
     * web search) rather than an MCP- or PHP-backed HAWKI tool.
     *
     * @throws ToolNotFoundException when the active provider has no native tool for the capability.
     */
    public function resolveNativeToolForCapability(
        string              $capabilityKey,
        AgentRequestContext $context,
        array               $toolSettings = []
    ): ProviderTool
    {
        $providerToolFactory = $context->provider->adapter->getNativeToolFactoryForCapability($capabilityKey);
        if (!$providerToolFactory) {
            throw ToolNotFoundException::forProviderNotSupportingCapability($capabilityKey);
        }

        $tool = $providerToolFactory($context, $toolSettings);

        return NativeToolResolvedFilterEvent::dispatch($tool, $capabilityKey, $context, $toolSettings)->getTool();
    }

    /**
     * Resolves the first active, online HAWKI tool on the current model that matches the capability key.
     *
     * Only tools whose `server.status` is {@see OnlineStatus::ONLINE} and whose `active` flag is set
     * are considered, preventing the agent from calling a tool whose MCP server is down.
     *
     * @throws ToolNotFoundException when no eligible tool is found for the capability.
     */
    public function resolveToolForCapability(
        string              $capabilityKey,
        AgentRequestContext $context,
        array               $toolSettings = []
    ): Tool
    {
        $hawkiTool = $context->model->tools->firstWhere(function (AiTool $tool) use ($capabilityKey) {
            return $tool->getEffectiveCapability() === $capabilityKey
                && $tool->server->status === OnlineStatus::ONLINE
                && $tool->active;
        });

        if (!$hawkiTool) {
            throw ToolNotFoundException::forCapability($capabilityKey);
        }

        $tool = $this->toolConverter->convert($hawkiTool, $toolSettings);

        return ToolForCapabilityResolvedFilterEvent::dispatch($tool, $capabilityKey, $context, $hawkiTool, $toolSettings)->getTool();
    }

    /**
     * Resolves a HAWKI tool by its exact `name` field on the current model.
     *
     * No online-status or active check is applied — the tool is resolved purely by name match.
     * Use {@see resolveToolForCapability()} when you need the online/active guard.
     *
     * @throws ToolNotFoundException when no tool with the given name exists on the model.
     */
    public function resolveToolByName(
        string              $toolName,
        AgentRequestContext $context,
        array               $toolSettings = []
    ): Tool
    {
        $hawkiTool = $context->model->tools->firstWhere(function (AiTool $tool) use ($toolName) {
            return $tool->name === $toolName;
        });

        if (!$hawkiTool) {
            throw ToolNotFoundException::forName($toolName);
        }

        $tool = $this->toolConverter->convert($hawkiTool, $toolSettings);

        return ToolByNameResolvedFilterEvent::dispatch($tool, $toolName, $context, $toolSettings)->getTool();
    }
}
