<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Services\Admin\DeletedRecords;
use App\Models\Ai\AiModel;
use App\Models\Ai\SystemModel;
use App\Services\Ai\SystemModels\SystemModelAssignmentException;
use App\Services\Ai\SystemModels\SystemModelAssignmentGuard;
use App\Services\Ai\SystemPrompts\SystemPromptRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @extends ConfigurationRepository<SystemModel>
 */
class SystemModelRepository extends ConfigurationRepository
{
    public const RESOURCE = 'system-models';
    protected const RELATIONS = ['prompts'];

    public function __construct(
        private readonly SystemPromptRepository $prompts,
        private readonly SystemModelAssignmentGuard $assignmentGuard,
    ) {
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => SystemModel::class, 'columns' => ['model_type', 'usage_type', 'model_id'], 'fields' => [
            $fields->select('model_type', array_values((new \ReflectionClass(WellKnownSystemModelTypes::class))->getConstants())), $fields->select('usage_type', ['main', 'external']),
            $fields->field('model_id', 'select', 'required|string|exists:ai_models,model_id', options: 'model_keys'),
            $fields->field('prompts', 'localized-text', 'nullable|array'),
        ]];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);
        $rules['model_type'] = [...explode('|', $rules['model_type']), Rule::unique('system_models')->where('usage_type', $values['usage_type'] ?? null)->ignore($id)];
        $rules['prompts'] = 'nullable|array:en_US,de_DE';
        $rules['prompts.en_US'] = 'nullable|string|max:100000';
        $rules['prompts.de_DE'] = 'nullable|string|max:100000';

        return $rules;
    }

    protected function prepare(Model $model, array &$data): void
    {
        parent::prepare($model, $data);
        $this->validateSystemModel($data);
    }

    protected function saved(Model $model, array $data, array $original): void
    {
        $originalSystemPromptKey = $original ? [
            'prompt_type' => $original['model_type'],
            'usage_type' => $original['usage_type'],
        ] : null;
        $promptKey = ['prompt_type' => $data['model_type'], 'usage_type' => $data['usage_type']];

        if (null !== $originalSystemPromptKey && $originalSystemPromptKey !== $promptKey) {
            $this->prompts->deleteForSystemModel($originalSystemPromptKey['prompt_type'], $originalSystemPromptKey['usage_type']);
        }

        if (WellKnownSystemModelTypes::TRANSLATION === $data['model_type']) {
            $this->prompts->deleteForSystemModel($promptKey['prompt_type'], $promptKey['usage_type']);
        } elseif (\array_key_exists('prompts', $data)) {
            $this->prompts->replaceForSystemModel($promptKey['prompt_type'], $promptKey['usage_type'], $data['prompts'] ?? []);
        }
    }

    protected function identity(Model $model): ?string
    {
        return DeletedRecords::systemModelIdentity((string) $model->getRawOriginal('usage_type'), (string) $model->getRawOriginal('model_type'));
    }

    protected function deleting(Model $model): void
    {
        $this->prompts->deleteForSystemModel(
            (string) $model->getRawOriginal('model_type'),
            (string) $model->getRawOriginal('usage_type'),
        );
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['prompts'] = DB::table('system_prompts')
            ->where('prompt_type', $row['model_type'])
            ->where('usage_type', $row['usage_type'])
            ->pluck('prompt', 'locale')
            ->all();

        return $result;
    }

    protected function versionAttributes(array $row): array
    {
        $prompts = DB::table('system_prompts')
            ->where('prompt_type', $row['model_type'])
            ->where('usage_type', $row['usage_type'])
            ->orderBy('locale')
            ->get()
            ->all();

        return ['system_prompts' => $prompts];
    }

    private function validateSystemModel(array $data): void
    {
        $model = AiModel::withoutGlobalScopes()->where('model_id', $data['model_id'])->firstOrFail();

        try {
            $this->assignmentGuard->assertAssignable($model, $data['usage_type']);
        } catch (SystemModelAssignmentException $exception) {
            $key = SystemModelAssignmentException::RESTRICTED === $exception->reason ? 'model_restricted' : 'system_model';
            throw ValidationException::withMessages(['model_id' => __('admin.errors.' . $key)]);
        }
    }
}
