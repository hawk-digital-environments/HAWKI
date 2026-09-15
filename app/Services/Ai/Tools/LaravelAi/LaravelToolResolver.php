<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use App\Services\Ai\Tools\ToolAuthorization;
use App\Services\Ai\Tools\LaravelAi\Events\NativeToolResolvedFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\ToolByNameResolvedFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\ToolForCapabilityResolvedFilterEvent;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;

#[Singleton]
class LaravelToolResolver
{
    public function __construct(private readonly LaravelToolConverter $toolConverter)
    {
    }

    public function resolveNativeToolForCapability(string $capabilityKey, AgentRequestContext $context, array $toolSettings = []): ProviderTool
    {
        app(ToolAuthorization::class)->authorizeNative($capabilityKey, $context);
        $factory = $context->provider->adapter->getNativeToolFactoryForCapability($capabilityKey);
        $tool = NativeToolResolvedFilterEvent::dispatch($factory($context, $toolSettings), $capabilityKey, $context, $toolSettings)->getTool();
        return app(NativeToolAuthorizations::class)->register($tool, $capabilityKey, $context);
    }

    public function canResolveNative(string $capabilityKey, AgentRequestContext $context): bool
    {
        try {
            app(ToolAuthorization::class)->authorizeNative($capabilityKey, $context);
            return true;
        } catch (ToolAccessException) {
            return false;
        }
    }

    public function resolveToolForCapability(string $capabilityKey, AgentRequestContext $context, array $toolSettings = []): Tool
    {
        $candidates = $context->model->tools()->withoutGlobalScopes()
            ->whereRaw('COALESCE(mapped_capability, capability) = ?', [$capabilityKey])->get();
        $failure = ToolAccessException::denied();
        foreach ($candidates as $record) {
            try {
                app(ToolAuthorization::class)->authorizeTool($record, $context);
            } catch (ToolAccessException $exception) {
                if ($exception->errorCode === 'TOOL_UNAVAILABLE') {
                    $failure = $exception;
                }
                continue;
            }
            $tool = $this->toolConverter->convert($record, $toolSettings);
            $tool = ToolForCapabilityResolvedFilterEvent::dispatch($tool, $capabilityKey, $context, $record, $toolSettings)->getTool();
            return new AuthorizedTool($tool, $record, $context);
        }
        throw $failure;
    }

    public function resolveToolByName(string $toolName, AgentRequestContext $context, array $toolSettings = [], ?string $capabilityKey = null): Tool
    {
        $record = AiTool::withoutGlobalScopes()->where('name', $toolName)->first();
        if (!$record) {
            throw ToolAccessException::denied();
        }
        app(ToolAuthorization::class)->authorizeTool($record, $context);
        if ($capabilityKey !== null && $record->getEffectiveCapability() !== $capabilityKey) {
            throw ToolAccessException::unavailable();
        }
        $tool = $this->toolConverter->convert($record, $toolSettings);
        $tool = ToolByNameResolvedFilterEvent::dispatch($tool, $toolName, $context, $toolSettings)->getTool();
        return new AuthorizedTool($tool, $record, $context);
    }
}
