<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Ai\AiTool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends ConfigurationRepository<AiTool>
 */
class ToolRepository extends ConfigurationRepository
{
    public const RESOURCE = 'tools';
    protected const RELATIONS = ['models'];
    protected const VERSION_RELATIONS = [['ai_model_tools', 'ai_tool_id', 'ai_model_id']];

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => AiTool::class, 'create' => false, 'delete' => false, 'columns' => ['name', 'type', 'active', 'mapped_capability'], 'fields' => [
            $fields->field('description', 'textarea', 'nullable|string|max:10000'), $fields->boolean('active'), $fields->text('mapped_capability'), $fields->multiple('models', 'models'),
        ]];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);
        $rules['models.*'] = 'integer|distinct|exists:ai_models,id';

        return $rules;
    }

    protected function saved(Model $model, array $data, array $original): void
    {
        $model->models()->sync($data['models']);
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['models'] = DB::table('ai_model_tools')->where('ai_tool_id', $row['id'])->pluck('ai_model_id')->map(static fn ($id) => (int) $id)->all();

        return $result;
    }
}
