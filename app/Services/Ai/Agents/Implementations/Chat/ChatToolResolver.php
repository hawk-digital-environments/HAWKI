<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat;


use App\Services\Ai\Agents\Implementations\Chat\Values\ToolTransferData;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;

readonly class ChatToolResolver
{
    public function __construct(
        private AiModelCapabilityRegistry $capabilityRegistry,
        private LaravelToolResolver       $laravelToolResolver
    )
    {
    }

    public function findTools(
        array               $toolTransferStrings,
        AgentRequestContext $context
    ): iterable
    {
        foreach ($toolTransferStrings as $toolTransferString) {
            if (!is_string($toolTransferString)) {
                // @todo exception
                throw new \InvalidArgumentException('Tool transfer strings must be an array of strings.');
            }

            $toolData = ToolTransferData::fromString($toolTransferString);

            if ($toolData->isCapability()) {
                yield $this->findToolByCapability($toolData, $context);
                continue;
            }

            if ($toolData->isTool()) {
                yield $this->findToolByName($toolData, $context);
                continue;
            }

            // @todo exception
            throw new \InvalidArgumentException('Tool transfer data must be either a capability or a tool name.');
        }
    }

    private function findToolByCapability(
        ToolTransferData    $toolData,
        AgentRequestContext $context
    ): Tool|ProviderTool
    {
        $capability = $this->capabilityRegistry->getDefinition($toolData->toolOrCapability);
        if (!$capability) {
            // @todo exception
            throw new \InvalidArgumentException('Capability not found: ' . $toolData->toolOrCapability);
        }

        if (!$toolData->innerTool) {
            // @todo exception
            throw new \InvalidArgumentException('Capability must have an inner tool specified: ' . $toolData->toolOrCapability);
        }

        $innerTool = $toolData->innerTool;
        if ($innerTool === 'auto') {
            if ($context->model->native_capabilities->has($capability->key)) {
                return $this->laravelToolResolver->resolveNativeToolForCapability($capability->key, $context, $toolData->settings);
            }

            return $this->laravelToolResolver->resolveToolForCapability($capability->key, $context, $toolData->settings);
        }

        if ($toolData->innerTool === 'native') {
            return $this->laravelToolResolver->resolveNativeToolForCapability($capability->key, $context, $toolData->settings);
        }

        return $this->laravelToolResolver->resolveToolByName($toolData->innerTool, $context, $toolData->settings);
    }

    private function findToolByName(
        ToolTransferData    $toolData,
        AgentRequestContext $context
    ): Tool
    {
        return $this->laravelToolResolver->resolveToolByName($toolData->toolOrCapability, $context, $toolData->settings);
    }
}
