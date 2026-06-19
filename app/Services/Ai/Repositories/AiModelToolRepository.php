<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

class AiModelToolRepository extends AbstractRepository
{

    public function assignTool(AiModel $model, AiTool $tool): void
    {
        $this->getQuery()
            ->updateOrCreate(
                ['ai_model_id' => $model->id, 'ai_tool_id' => $tool->id],
                [
                    'type' => $tool->type
                ]
            );
    }

    public function unassignTool(AiModel $model, AiTool $tool): void
    {
        $this->getQuery()
            ->where('ai_model_id', $model->id)
            ->where('ai_tool_id', $tool->id)
            ->delete();
    }
}
