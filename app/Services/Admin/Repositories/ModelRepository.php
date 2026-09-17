<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelDescription;
use App\Models\Ai\AiProvider;
use App\Models\Ai\SystemModel;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use App\Services\Ai\SystemModels\SystemModelAssignmentException;
use App\Services\Ai\SystemModels\SystemModelAssignmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @extends ConfigurationRepository<AiModel>
 */
class ModelRepository extends ConfigurationRepository
{
    public const RESOURCE = 'models';
    protected const RELATIONS = ['descriptions', 'tools', 'usage_rules'];
    protected const VERSION_RELATIONS = [['ai_model_descriptions', 'ai_model_id', 'locale'], ['ai_model_tools', 'ai_model_id', 'ai_tool_id'], ['ai_model_usage_rules', 'ai_model_id', 'usage_type']];

    public function __construct(private readonly SystemModelAssignmentGuard $assignmentGuard)
    {
    }

    public function refreshModel(?string $id): array
    {
        $model = AiModel::withoutGlobalScopes()->findOrFail($id);
        $provider = AiProvider::withoutGlobalScopes()->findOrFail($model->provider_id);

        try {
            $info = app(ModelInfoFetcher::class)->fetchSingle(app(AiProviderProxyResolver::class)->resolve($provider), $model->model_id);
            abort_unless(null !== $info, 422, __('admin.errors.metadata'));

            foreach (['limits', 'pricing', 'flags', 'native_capabilities', 'deprecation_date', 'documentation_url'] as $key) {
                if (null !== $info->{$key}) {
                    $model->setAttribute($key, $info->{$key});
                }
            }

            $model->setAttribute('admin_managed', true);
            $model->save();
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, __('admin.errors.metadata'));
        }

        return ['refreshed' => true];
    }

    public function checkStatus(): array
    {
        app(ModelStatusUpdater::class)->run();

        return ['checked' => true];
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => AiModel::class, 'columns' => ['label', 'model_id', 'provider_id', 'active', 'status'], 'fields' => [
            $fields->reference('provider_id', 'ai_providers', 'providers'), $fields->text('model_id', true) + ['immutable' => true], $fields->text('label', true),
            $fields->field('descriptions', 'localized-text', 'nullable|array'),
            $fields->boolean('active'), $fields->text('model_type', true) + ['default' => 'chat'],
            $fields->field('documentation_url', 'url', 'nullable|url:http,https|max:2000'),
            $fields->field('deprecation_date', 'datetime', 'nullable|date'),
            ...array_map($fields->json(...), ['input', 'output', 'parameters', 'native_capabilities', 'settings', 'limits', 'pricing', 'flags']),
            $fields->multiple('tools', 'tools'), $fields->multiple('usage_rules', ['main', 'external']),
        ]];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);
        $rules['model_id'] = ['required', 'string', 'max:255', Rule::unique('ai_models')->ignore($id)];
        $rules['descriptions'] = 'nullable|array:en_US,de_DE';
        $rules['descriptions.en_US'] = 'nullable|string|max:30000';
        $rules['descriptions.de_DE'] = 'nullable|string|max:30000';
        $rules['tools.*'] = 'integer|distinct|exists:ai_tools,id';
        $rules['usage_rules.*'] = 'string|distinct|in:main,external';

        foreach (['input', 'output'] as $key) {
            $rules[$key . '.*'] = 'string|in:text,image,audio,video';
        }

        return $rules;
    }

    protected function prepare(Model $model, array &$data): void
    {
        parent::prepare($model, $data);
        $this->validateModelUse($model, $data);

        if (!$model->exists) {
            foreach (['input', 'output', 'parameters', 'native_capabilities', 'settings', 'limits', 'pricing', 'flags'] as $key) {
                $data[$key] ??= [];
            }
        }
    }

    protected function saved(Model $model, array $data, array $original): void
    {
        if (\array_key_exists('descriptions', $data)) {
            $modelId = $model->getKey();

            foreach (['en_US', 'de_DE'] as $locale) {
                $description = $data['descriptions'][$locale] ?? null;

                if (!\is_string($description) || '' === trim($description)) {
                    DB::table('ai_model_descriptions')
                        ->where('ai_model_id', $modelId)
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                $localized = AiModelDescription::withoutGlobalScopes()->firstOrNew([
                    'ai_model_id' => $modelId,
                    'locale' => $locale,
                ]);
                $localized->setAttribute('description', $description);
                $localized->setAttribute('admin_managed', true);
                $localized->save();
            }
        }

        $model->tools()->sync($data['tools']);
        DB::table('ai_model_usage_rules')->where('ai_model_id', $model->id)->delete();

        foreach ($data['usage_rules'] as $usage) {
            DB::table('ai_model_usage_rules')->insert(['ai_model_id' => $model->id, 'usage_type' => $usage, 'created_at' => now(), 'updated_at' => now()]);
        }

    }

    protected function deleting(Model $model): void
    {
        if (SystemModel::withoutGlobalScopes()->where('model_id', $model->model_id)->exists()) {
            throw ValidationException::withMessages(['model' => __('admin.errors.model_in_use')]);
        }
    }

    protected function identity(Model $model): ?string
    {
        return (string) $model->getRawOriginal('model_id');
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['descriptions'] = DB::table('ai_model_descriptions')
            ->where('ai_model_id', $row['id'])
            ->pluck('description', 'locale')
            ->all();
        $result['tools'] = DB::table('ai_model_tools')->where('ai_model_id', $row['id'])->pluck('ai_tool_id')->map(static fn ($id) => (int) $id)->all();
        $result['usage_rules'] = DB::table('ai_model_usage_rules')->where('ai_model_id', $row['id'])->pluck('usage_type')->all();

        return $result;
    }

    private function validateModelUse(AiModel $model, array $data): void
    {
        if (!$model->exists) {
            return;
        }

        $providerActive = AiProvider::withoutGlobalScopes()->whereKey($data['provider_id'])->value('active');

        try {
            $this->assignmentGuard->assertConfigurationAllowed(
                $model,
                $data['active'],
                (bool) $providerActive,
                $data['usage_rules'],
            );
        } catch (SystemModelAssignmentException $exception) {
            $key = 'active';
            $message = 'model_in_use';
            throw ValidationException::withMessages([$key => __('admin.errors.' . $message)]);
        }
    }
}
