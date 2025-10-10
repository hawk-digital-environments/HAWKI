<?php
declare(strict_types=1);


namespace App\Services\AI\Db;


use App\Models\AiModel as DatabaseAiModel;
use App\Models\AiModelStatus;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\ModelOnlineStatus;

class ModelStatusDb
{
    private ?array $loadedStatuses = null;

    public function getStatus(AiModel $model): ModelOnlineStatus
    {
        // Check if this model exists in the database (DB-configured models)
        $dbModel = DatabaseAiModel::where('model_id', $model->getId())->first();
        
        // If it's a database-configured model, always return ONLINE
        if ($dbModel) {
            return ModelOnlineStatus::ONLINE;
        }
        
        // For config-based models, use the normal status checking logic
        if ($this->loadedStatuses === null) {
            $this->loadedStatuses = AiModelStatus::all(['model_id', 'status'])->keyBy('model_id')->toArray();
        }

        if (isset($this->loadedStatuses[$model->getId()])) {
            return ModelOnlineStatus::from($this->loadedStatuses[$model->getId()]['status']);
        }

        return ModelOnlineStatus::UNKNOWN;
    }

    public function setModelStatus(AiModel $model, ModelOnlineStatus $status): void
    {
        $this->loadedStatuses[$model->getId()] = ['model_id' => $model->getId(), 'status' => $status->value];

        AiModelStatus::updateOrCreate(
            ['model_id' => $model->getId()],
            ['status' => $status]
        );
    }
}
