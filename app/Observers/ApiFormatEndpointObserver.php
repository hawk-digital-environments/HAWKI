<?php

namespace App\Observers;

use App\Models\ApiFormatEndpoint;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class ApiFormatEndpointObserver
{
    /**
     * Handle the ApiFormatEndpoint "created" event.
     */
    public function created(ApiFormatEndpoint $endpoint): void
    {
        $this->invalidateAiCaches('API format endpoint created', $endpoint);
    }

    /**
     * Handle the ApiFormatEndpoint "updated" event.
     */
    public function updated(ApiFormatEndpoint $endpoint): void
    {
        // Check if relevant fields changed
        $relevantFields = ['name', 'path', 'method', 'is_active'];
        
        if ($endpoint->wasChanged($relevantFields)) {
            $this->invalidateAiCaches('API format endpoint updated', $endpoint);
        }
    }

    /**
     * Handle the ApiFormatEndpoint "deleted" event.
     */
    public function deleted(ApiFormatEndpoint $endpoint): void
    {
        $this->invalidateAiCaches('API format endpoint deleted', $endpoint);
    }

    /**
     * Handle the ApiFormatEndpoint "restored" event.
     */
    public function restored(ApiFormatEndpoint $endpoint): void
    {
        $this->invalidateAiCaches('API format endpoint restored', $endpoint);
    }

    /**
     * Handle the ApiFormatEndpoint "force deleted" event.
     */
    public function forceDeleted(ApiFormatEndpoint $endpoint): void
    {
        $this->invalidateAiCaches('API format endpoint force deleted', $endpoint);
    }

    /**
     * Invalidate AI caches and log the action
     */
    private function invalidateAiCaches(string $reason, ApiFormatEndpoint $endpoint): void
    {
        try {
            // Clear specific endpoint cache first
            $endpoint->clearUrlCache();

            // Clear factory caches if this affects critical endpoints
            if (in_array($endpoint->name, ['models.list', 'chat.create'])) {
                $factory = app(AIProviderFactory::class);
                $factory->clearAllCaches();
            }
        } catch (\Exception $e) {
            Log::error("Failed to clear AI caches after endpoint change", [
                'reason' => $reason,
                'endpoint_id' => $endpoint->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
