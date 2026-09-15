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

    /**
     * Returns the failure that prevents the native implementation from being used, or null when it is usable.
     * Callers need the distinction: a missing grant is a 403, a model without a usable implementation is a 422.
     */
    public function nativeResolutionFailure(string $capabilityKey, AgentRequestContext $context): ?ToolAccessException
    {
        try {
            app(ToolAuthorization::class)->authorizeNative($capabilityKey, $context);
            return null;
        } catch (ToolAccessException $exception) {
            return $exception;
        }
    }

    /**
     * A caller that is denied on one path but merely blocked by configuration on another still holds a grant,
     * so the configuration failure (422) is the honest answer and keeps clients from re-fetching authorization.
     */
    public static function preferredFailure(ToolAccessException ...$failures): ToolAccessException
    {
        foreach ($failures as $failure) {
            if ($failure->errorCode === 'TOOL_UNAVAILABLE') {
                return $failure;
            }
        }
        return $failures[0] ?? ToolAccessException::unavailable();
    }

    public function resolveToolForCapability(string $capabilityKey, AgentRequestContext $context, array $toolSettings = []): Tool
    {
        $candidates = $context->model->tools()->withoutGlobalScopes()
            ->whereRaw('COALESCE(mapped_capability, capability) = ?', [$capabilityKey])->get();
        // Without a single candidate nothing was denied; the capability simply has no implementation here.
        $failure = null;
        foreach ($candidates as $record) {
            try {
                app(ToolAuthorization::class)->authorizeTool($record, $context);
            } catch (ToolAccessException $exception) {
                $failure = $failure === null ? $exception : self::preferredFailure($failure, $exception);
                continue;
            }
            $tool = $this->toolConverter->convert($record, $toolSettings);
            $tool = ToolForCapabilityResolvedFilterEvent::dispatch($tool, $capabilityKey, $context, $record, $toolSettings)->getTool();
            return new AuthorizedTool($tool, $record, $context);
        }
        throw $failure ?? ToolAccessException::unavailable();
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
