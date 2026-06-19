<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google\Request;


use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Support\Facades\Http;

class GoogleModelStatusRequest extends AbstractRequest
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
            $response = Http::timeout(10)
                ->get($this->provider->getConfig()->getPingUrl(), [
                    'key' => $this->provider->getConfig()->getApiKey(),
                ]);

            $stats = json_decode((string)$response, true)['models'];
            $statusCollection->setAllOffline();

            foreach ($stats as $modelStat) {
                $statusCollection->setStatusById($modelStat['name'], ModelOnlineStatus::ONLINE);
            }
        } catch (\Throwable) {
            $statusCollection->setAllOffline();
        }
    }
}
