<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\SystemModel;
use App\Models\User;
use App\Casts\Contracts\CastableInstanceInterface;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @extends ConfigurationRepository<AiProvider>
 */
class ProviderRepository extends ConfigurationRepository
{
    public const RESOURCE = 'providers';

    /** The provider's model list; with `$onlyNew` reduced to models that are not configured yet (model ids are unique across providers). */
    public function provider(?string $id, bool $onlyNew = false): array
    {
        $provider = AiProvider::withoutGlobalScopes()->findOrFail($id);
        $proxy = app(AiProviderProxyResolver::class)->resolve($provider);
        $known = $onlyNew ? AiModel::withoutGlobalScopes()->pluck('model_id')->flip() : collect();

        try {
            $models = $proxy->adapter->getModels($proxy);

            return ['models' => $models
                ->reject(static fn ($model) => $known->has($model->model_id))
                ->map(static fn ($model) => ['model_id' => $model->model_id, 'label' => $model->label ?? $model->model_id])->values()->all()];
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, __('admin.errors.connection'));
        }
    }

    /** Metadata the provider and the enrichment pipeline know about one model, keyed like the model editor's fields. */
    public function inspect(?string $id, string $modelId): array
    {
        $provider = AiProvider::withoutGlobalScopes()->findOrFail($id);
        $proxy = app(AiProviderProxyResolver::class)->resolve($provider);

        try {
            $info = app(ModelInfoFetcher::class)->fetchSingle($proxy, $modelId);
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, __('admin.errors.metadata'));
        }

        abort_unless(null !== $info, 422, __('admin.errors.metadata'));
        $model = [];

        foreach (['label', 'model_type', 'input', 'output', 'parameters', 'settings', 'documentation_url', 'deprecation_date', 'native_capabilities', 'limits', 'pricing', 'flags'] as $key) {
            $value = $info->getAttribute($key);

            if ($value instanceof CastableInstanceInterface) {
                $value = $value->toArray();
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DATE_ATOM);
            }

            if (null === $value || [] === $value || '' === $value) {
                continue;
            }

            $model[$key] = $value;
        }

        return ['model' => $model];
    }

    public function import(User $actor): array
    {
        foreach ([\App\Services\Admin\Permission::MODELS_MANAGE, \App\Services\Admin\Permission::MCP_MANAGE] as $permission) {
            app(\App\Services\Admin\PermissionService::class)->authorize($actor, $permission);
        }

        Artisan::queue('ai:config:import');

        return ['queued' => true];
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => AiProvider::class, 'columns' => ['name', 'provider_id', 'adapter_key', 'active', 'api_key_set'], 'fields' => [
            $fields->text('name', true), $fields->field('icon', 'provider-icon', 'nullable|array'), $fields->text('provider_id', true) + ['immutable' => true],
            $fields->select('adapter_key', array_values((new \ReflectionClass(\App\Services\Ai\Providers\Adapters\WellKnownAdapterKeys::class))->getConstants())),
            $fields->boolean('active'), $fields->field('api_url', 'url', 'nullable|url:http,https|max:2000'),
            $fields->field('model_status_url', 'url', 'nullable|url:http,https|max:2000'), ...$fields->secrets(), $fields->json('settings'),
        ]];
    }

    protected function rowAttributes(array $row): array
    {
        $provider = new AiProvider();
        $provider->setRawAttributes($row);

        return ['icon' => $provider->icon, 'icon_url' => $provider->icon_url, 'icon_url_dark' => $provider->icon_url_dark];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);
        $rules['provider_id'] = ['required', 'alpha_dash', 'max:100', Rule::unique('ai_providers')->ignore($id)];

        return $rules;
    }

    protected function prepare(Model $model, array &$data): void
    {
        parent::prepare($model, $data);

        if (\array_key_exists('icon', $data)) {
            $data['icon'] = app(\App\Services\Admin\ProviderIconService::class)->resolve($data['icon'], $model->getAttribute('icon'));
        }

        if (!app(ProviderAdapterRegistry::class)->has($data['adapter_key'])) {
            throw ValidationException::withMessages(['adapter_key' => __('admin.errors.adapter')]);
        }

        if (!$data['active'] && $model->exists) {
            $keys = AiModel::withoutGlobalScopes()->where('provider_id', $model->getKey())->pluck('model_id');

            if (SystemModel::withoutGlobalScopes()->whereIn('model_id', $keys)->exists()) {
                throw ValidationException::withMessages(['active' => __('admin.errors.model_in_use')]);
            }
        }
    }

    protected function deleting(Model $model): void
    {
        if (AiModel::withoutGlobalScopes()->where('provider_id', $model->getKey())->exists()) {
            throw ValidationException::withMessages(['provider' => __('admin.errors.provider_in_use')]);
        }
    }
}
