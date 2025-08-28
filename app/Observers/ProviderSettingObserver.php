<?php

namespace App\Observers;

use App\Models\ProviderSetting;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class ProviderSettingObserver
{
    /**
     * Handle the ProviderSetting "created" event.
     */
    public function created(ProviderSetting $providerSetting): void
    {
        $this->invalidateAiCaches('Provider setting created', $providerSetting);
    }

    /**
     * Handle the ProviderSetting "updated" event.
     */
    public function updated(ProviderSetting $providerSetting): void
    {
        // Check if relevant fields changed
        $relevantFields = ['is_active', 'api_format_id', 'provider_name', 'api_key', 'additional_settings'];
        
        if ($providerSetting->wasChanged($relevantFields)) {
            $this->invalidateAiCaches('Provider setting updated', $providerSetting);
        }
    }

    /**
     * Handle the ProviderSetting "deleted" event.
     */
    public function deleted(ProviderSetting $providerSetting): void
    {
        $this->invalidateAiCaches('Provider setting deleted', $providerSetting);
    }

    /**
     * Handle the ProviderSetting "restored" event.
     */
    public function restored(ProviderSetting $providerSetting): void
    {
        $this->invalidateAiCaches('Provider setting restored', $providerSetting);
    }

    /**
     * Handle the ProviderSetting "force deleted" event.
     */
    public function forceDeleted(ProviderSetting $providerSetting): void
    {
        $this->invalidateAiCaches('Provider setting force deleted', $providerSetting);
    }

    /**
     * Invalidate AI caches and log the action
     */
    private function invalidateAiCaches(string $reason, ProviderSetting $provider): void
    {
        try {
            // Clear specific provider caches first
            $provider->clearUrlCaches();

            // Clear factory caches
            $factory = app(AIProviderFactory::class);
            $factory->clearAllCaches();

            Log::info("AI caches cleared", [
                'reason' => $reason,
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider_name,
                'is_active' => $provider->is_active
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to clear AI caches after provider setting change", [
                'reason' => $reason,
                'provider_id' => $provider->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
