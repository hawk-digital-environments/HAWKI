<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Support\Facades\Http;

class AnthropicModelStatusRequest extends AbstractRequest
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
            // No ping URL configured, mark all as unknown
            $statusCollection->markAllAs(ModelOnlineStatus::UNKNOWN);
            return;
        }
        
        try {
            $apiKey = $this->provider->getConfig()->getApiKey();
            
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01'
            ])->timeout(5)->get($pingUrl);
            
            if ($response->successful()) {
                // Anthropic doesn't provide a models endpoint like OpenAI
                // If the API responds, assume all configured models are available
                $statusCollection->markAllAs(ModelOnlineStatus::ONLINE);
            } else {
                $statusCollection->markAllAs(ModelOnlineStatus::OFFLINE);
            }
        } catch (\Exception $e) {
            \Log::warning('Anthropic status check failed: ' . $e->getMessage());
            $statusCollection->markAllAs(ModelOnlineStatus::UNKNOWN);
        }
    }
}
