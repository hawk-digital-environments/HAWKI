<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelUsageRule;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

class AiModelUsageRuleRepository extends AbstractRepository
{
    public function assignTypeToModel(AiModel $model, string $type): AiModelUsageRule
    {
        return $this->getQuery()->updateOrCreate(
            [
                'ai_model_id' => $model->id,
                'usage_type' => $type,
            ]
        );
    }

    public function removeTypeFromModel(AiModel $model, string $type): void
    {
        $this->getQuery()->where([
            'ai_model_id' => $model->id,
            'usage_type' => $type,
        ])->delete();
    }

    public function toggleTypeOfModel(AiModel $model, string $type, bool $enabled): void
    {
        if ($enabled) {
            $this->assignTypeToModel($model, $type);
        } else {
            $this->removeTypeFromModel($model, $type);
        }
    }
}
