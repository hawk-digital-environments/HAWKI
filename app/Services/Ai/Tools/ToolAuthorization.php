<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Models\User;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Eloquent\Builder;

final class ToolAuthorization
{
    /** Applies to all public tool queries, including relationship linkage and includes, before pagination. */
    public function discoverable(Builder $query, ?User $actor): Builder
    {
        return $query->whereIn('ai_tools.access_rule', ToolAccessRules::allowedRules($actor))
            ->where('ai_tools.active', true)
            ->whereHas('models', static fn (Builder $models) => $models->where('ai_models.active', true)
                ->where('settings->tool_calling', true)
                ->whereHas('usageRules', static fn ($rules) => $rules->where('usage_type', app(\App\Services\System\UsageTypes\UsageContext::class)->getForGiven(null)))
                ->whereHas('provider', static fn ($q) => $q->where('active', true)));
    }

    public function actor(AgentRequestContext $context): ?User
    {
        return $context->actorId === null ? null : User::withoutGlobalScopes()->find($context->actorId);
    }

    public function authorizeTool(AiTool $tool, AgentRequestContext $context): AiTool
    {
        $current = AiTool::withoutGlobalScopes()->find($tool->getKey());
        if (!$current || !in_array($current->access_rule, ToolAccessRules::allowedRules($this->actor($context)), true)) {
            throw ToolAccessException::denied();
        }
        $model = $this->currentModel($context);
        if (!$current->active || !$model->settings->canUseTools()
            || !$model->tools()->withoutGlobalScopes()->whereKey($current->getKey())->exists()) {
            throw ToolAccessException::unavailable();
        }
        if ($current->type === ToolType::MCP) {
            $server = $current->server()->first();
            if ($tool->relationLoaded('server') && $server && $tool->server
                && $server->getRawOriginal() !== $tool->server->getRawOriginal()) {
                throw ToolAccessException::unavailable();
            }
            if (!$server || $server->status !== OnlineStatus::ONLINE || !$current->mcp_config) {
                throw ToolAccessException::unavailable();
            }
        }
        // A resolved implementation must not survive a configuration replacement.
        foreach (['name', 'capability', 'mapped_capability', 'type', 'class_name', 'mcp_server_id', 'mcp_name', 'mcp_config'] as $attribute) {
            if ($current->getRawOriginal($attribute) !== $tool->getRawOriginal($attribute)) {
                throw ToolAccessException::unavailable();
            }
        }
        return $current;
    }

    public function authorizeNative(string $capability, AgentRequestContext $context): void
    {
        $rule = ToolAccessRules::nativeRule($capability);
        if (!$rule || !in_array($rule, ToolAccessRules::allowedRules($this->actor($context)), true)) {
            throw ToolAccessException::denied();
        }
        $model = $this->currentModel($context);
        if (!$model->settings->canUseTools() || !$model->settings->canUseNativeCapabilities()
            || !$model->native_capabilities->has($capability)
            || !$context->provider->adapter->getNativeToolFactoryForCapability($capability)) {
            throw ToolAccessException::unavailable();
        }
    }

    public function nativeModelIds(string $capability, ?User $actor): array
    {
        if (!in_array(ToolAccessRules::nativeRule($capability), ToolAccessRules::allowedRules($actor), true)) {
            return [];
        }
        $ids = [];
        foreach (AiModel::query()->whereHas('usageRules', static fn ($rules) => $rules->where('usage_type', app(\App\Services\System\UsageTypes\UsageContext::class)->getForGiven(null)))->get() as $model) {
            if (!$model->active || !$model->settings->canUseTools()
                || !$model->settings->canUseNativeCapabilities() || !$model->native_capabilities->has($capability)) {
                continue;
            }
            $provider = app(AiProviderProxyResolver::class)->resolveForModel($model);
            if ($provider->active && $provider->adapter->getNativeToolFactoryForCapability($capability)) {
                $ids[] = (string) $model->getKey();
            }
        }
        return $ids;
    }

    public function canDiscoverCapability(string $capability, ?User $actor): bool
    {
        return $this->nativeModelIds($capability, $actor) !== [] || $this->discoverable(AiTool::withoutGlobalScopes(), $actor)
            ->whereRaw('COALESCE(mapped_capability, capability) = ?', [$capability])->exists();
    }

    private function currentModel(AgentRequestContext $context): AiModel
    {
        $model = AiModel::withoutGlobalScopes()->find($context->model->getKey());
        $provider = $model?->provider()->withoutGlobalScopes()->first();
        if (!$model || $model->model_id !== $context->model->model_id || !$model->active || !$provider?->active
            || (int) $provider->getKey() !== (int) $context->provider->getRealProvider()->getKey()
            || !$model->usageRules()->where('usage_type', $context->usageType)->exists()) {
            throw ToolAccessException::unavailable();
        }
        foreach (['adapter_key', 'api_url', 'api_key', 'additional_config', 'settings'] as $attribute) {
            if ($provider->getRawOriginal($attribute) !== $context->provider->getRealProvider()->getRawOriginal($attribute)) {
                throw ToolAccessException::unavailable();
            }
        }
        return $model;
    }
}
