<?php

namespace App\Observers;

use App\Models\ApiFormat;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class ApiFormatObserver
{
    /**
     * Handle the ApiFormat "created" event.
     */
    public function created(ApiFormat $apiFormat): void
    {
        $this->invalidateAiCaches('API format created', $apiFormat);
    }

    /**
     * Handle the ApiFormat "updated" event.
     */
    public function updated(ApiFormat $apiFormat): void
    {
        // Check if relevant fields changed
        $relevantFields = ['unique_name', 'display_name', 'base_url', 'metadata'];
        
        if ($apiFormat->wasChanged($relevantFields)) {
            $this->invalidateAiCaches('API format updated', $apiFormat);
        }
    }

    /**
     * Handle the ApiFormat "deleted" event.
     */
    public function deleted(ApiFormat $apiFormat): void
    {
        $this->invalidateAiCaches('API format deleted', $apiFormat);
    }

    /**
     * Handle the ApiFormat "restored" event.
     */
    public function restored(ApiFormat $apiFormat): void
    {
        $this->invalidateAiCaches('API format restored', $apiFormat);
    }

    /**
     * Handle the ApiFormat "force deleted" event.
     */
    public function forceDeleted(ApiFormat $apiFormat): void
    {
        $this->invalidateAiCaches('API format force deleted', $apiFormat);
    }

    /**
     * Invalidate AI caches and log the action
     */
    private function invalidateAiCaches(string $reason, ApiFormat $apiFormat): void
    {
        try {
            // Clear related caches first
            $apiFormat->clearRelatedCaches();

            // Clear factory caches
            $factory = app(AIProviderFactory::class);
            $factory->clearAllCaches();
        } catch (\Exception $e) {
            Log::error("Failed to clear AI caches after API format change", [
                'reason' => $reason,
                'api_format_id' => $apiFormat->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
