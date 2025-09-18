<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg\Request;


use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Support\Facades\Http;

class GwdgModelStatusRequest extends AbstractRequest
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
            $stats = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'];

            $statusCollection->setAllOffline();
            foreach ($stats as $modelStat) {
                $status = match ($modelStat['status']) {
                    'ready' => ModelOnlineStatus::ONLINE,
                    'offline' => ModelOnlineStatus::OFFLINE,
                    default => ModelOnlineStatus::UNKNOWN,
                };
                $statusCollection->setStatusById($modelStat['id'], $status);
            }
        } catch (\Throwable) {
            $statusCollection->setAllOffline();
        }
    }
}
