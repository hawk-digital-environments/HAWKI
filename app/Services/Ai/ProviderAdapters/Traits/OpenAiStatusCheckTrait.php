<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Traits;


use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\StatusCheck\ModelStatusFetcher;

trait OpenAiStatusCheckTrait
{
    private function runOpenAiStatusCheck(
        AiModelOnlineStatusCollection $statusCollection,
        ModelStatusFetcher            $fetcher,
        string|null                   $alternativeRoute = null
    ): void
    {
        /* @see https://developers.openai.com/api/reference/resources/models/methods/list */
        foreach ($fetcher->getExtract($alternativeRoute ?? '/models', 'data.*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
