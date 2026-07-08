<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Repositories;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelUsageRule;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

/**
 * Repository for {@see AiModelUsageRule} entities.
 *
 * Usage rules associate a model with one or more allowed usage types (e.g. "chat",
 * "batch"). Each rule is a (model, usage_type) pair; this repository provides
 * create/delete helpers and a combined toggle operation.
 */
class AiModelUsageRuleRepository extends AbstractRepository
{
    /**
     * Creates a usage rule assigning $type to $model.
     *
     * No-op (returns the existing rule) when the assignment already exists.
     */
    public function assignTypeToModel(AiModel $model, string $type): AiModelUsageRule
    {
        return $this->getQuery()->updateOrCreate(
            [
                'ai_model_id' => $model->id,
                'usage_type' => $type,
            ]
        );
    }

    /** Deletes the usage rule for $type on $model. No-op when absent. */
    public function removeTypeFromModel(AiModel $model, string $type): void
    {
        $this->getQuery()->where([
            'ai_model_id' => $model->id,
            'usage_type' => $type,
        ])->delete();
    }

    /** Assigns or removes $type from $model based on $enabled. */
    public function toggleTypeOfModel(AiModel $model, string $type, bool $enabled): void
    {
        if ($enabled) {
            $this->assignTypeToModel($model, $type);
        } else {
            $this->removeTypeFromModel($model, $type);
        }
    }
}
