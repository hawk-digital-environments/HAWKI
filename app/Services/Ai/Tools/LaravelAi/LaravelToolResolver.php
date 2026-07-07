<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Values\AgentRequestContext;
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
            // @todo exception
            throw new \InvalidArgumentException('Provider does not support native tool for capability: ' . $capabilityKey);
        }

        $tool = $providerToolFactory($context, $toolSettings);

        // @todo filter event $tool $capabilityKey $context $toolSettings

        return $tool;
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
            // @todo exception
            throw new \InvalidArgumentException('No tool found for capability: ' . $capabilityKey);
        }

        $tool = $this->toolConverter->convert($hawkiTool, $toolSettings);

        // @todo filter event $tool $capabilityKey $context $hawkiTool $toolSettings

        return $tool;
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
            // @todo exception
            throw new \InvalidArgumentException('No tool found for name: ' . $toolName);
        }

        $tool = $this->toolConverter->convert($hawkiTool, $toolSettings);

        // @todo filter event $tool $toolName $context $toolSettings

        return $tool;
    }
}
