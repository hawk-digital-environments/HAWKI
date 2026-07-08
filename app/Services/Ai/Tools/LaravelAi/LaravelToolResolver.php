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

#[Singleton]
readonly class LaravelToolResolver
{
    public function __construct(
        private LaravelToolConverter $toolConverter
    )
    {
    }

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
