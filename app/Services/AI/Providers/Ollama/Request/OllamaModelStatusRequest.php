<?php

namespace App\Services\AI\Providers\Ollama\Request;

use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Support\Facades\Http;

class OllamaModelStatusRequest extends AbstractRequest
{
    public function __construct(
        private readonly ModelProviderInterface $provider
    )
    {
    }

    public function execute(AiModelStatusCollection $statusCollection): void
    {
        $pingUrl = $this->provider->getConfig()->getPingUrl();
        if ($pingUrl === null) {
            $statusCollection->setAllOnline();
            return;
        }

        try {
            $response = Http::withToken($this->provider->getConfig()->getApiKey())
                ->timeout(10) // Set a short timeout
                ->get($this->provider->getConfig()->getPingUrl());

            $stats = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR)['models'];
            $statusCollection->setAllOffline();

            foreach ($stats as $modelStat) {
                $modelStat['name'] = str_replace(":latest", "", $modelStat['name']);
                $statusCollection->setStatusById($modelStat['name'], ModelOnlineStatus::ONLINE);
            }
        } catch (\Throwable) {
            $statusCollection->setAllOffline();
        }
    }
}
