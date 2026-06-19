<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Ai\Values\ParameterSource;

class GwdgAdapter extends OpenAiLikeAdapter
{
    protected string|null $baseUrl = 'https://chat-ai.academiccloud.de/v1';

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        foreach ($this->createStatusFetcher($source)->getExtract('/models', 'data.*') as $modelData) {
            $status = match ($modelData['status']) {
                'ready' => OnlineStatus::ONLINE,
                'offline' => OnlineStatus::OFFLINE,
                default => OnlineStatus::UNKNOWN,
            };
            $statusCollection->set($modelData['id'], $status);

            $demandInt = $modelData['demand'] ?? 0;
            $demand = match (true) {
                $demandInt >= 4 => ModelDemand::HIGH,
                $demandInt >= 2 => ModelDemand::MEDIUM,
                default => ModelDemand::LOW,
            };
            $demandCollection->set($modelData['id'], $demand);
        }
    }
}
