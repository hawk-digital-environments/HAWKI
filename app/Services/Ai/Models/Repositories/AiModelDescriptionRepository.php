<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Repositories;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelDescription;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;

class AiModelDescriptionRepository extends AbstractRepositoryWithContextualScopes
{
    public function assignDescriptionToModel(AiModel $model, AiModelDescription $description): AiModelDescription
    {
        return $this->getQueryWithoutContextualScopes()
            ->updateOrCreate(
                [
                    'ai_model_id' => $model->id,
                    'locale' => $description->locale
                ],
                [
                    'description' => $description->description
                ]
            );
    }

}
