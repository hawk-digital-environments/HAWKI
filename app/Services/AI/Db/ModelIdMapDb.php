<?php
declare(strict_types=1);


namespace App\Services\AI\Db;


use App\Events\AiModelNumericIdAssignedEvent;
use App\Models\AiModelIdMap;
use App\Services\AI\Value\AiModel;

class ModelIdMapDb
{
    private ?array $modelIdMap = null;
    
    /**
     * Get the numeric ID for a given model ID, or assign a new one if it doesn't exist.
     * The frontend sync uses numeric IDs for efficiency, therefore we need to map the model id string to a numeric ID.
     *
     * @param AiModel $model The AI model for which to get or assign a numeric ID.
     * @return int The numeric ID associated with the model ID.
     */
    public function getOrAssignNumericId(AiModel $model): int
    {
        $this->populateMap();
        
        $modelId = $model->getId();
        if (isset($this->modelIdMap[$modelId])) {
            return $this->modelIdMap[$modelId];
        }
        
        $newId = AiModelIdMap::create(['model_id' => $modelId])->id;
        $this->modelIdMap[$modelId] = $newId;
        
        AiModelNumericIdAssignedEvent::dispatch($model);
        
        return $newId;
    }
    
    /**
     * Get the model ID string for a given numeric ID.
     *
     * @param int $id The numeric ID to look up.
     * @return string|null The model ID associated with the numeric ID, or null if not found.
     */
    public function getModelIdByNumeric(int $id): ?string
    {
        $this->populateMap();
        
        $flippedMap = array_flip($this->modelIdMap);
        return $flippedMap[$id] ?? null;
    }
    
    private function populateMap(): void
    {
        if ($this->modelIdMap !== null) {
            return;
        }
        
        $this->modelIdMap = [];
        foreach (AiModelIdMap::query()->get(['id', 'model_id']) as $row) {
            $this->modelIdMap[$row->model_id] = $row->id;
        }
    }
}
