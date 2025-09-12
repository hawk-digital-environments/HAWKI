<?php

namespace App\Observers;

use App\Models\LanguageModel;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class LanguageModelObserver
{
    /**
     * Handle the LanguageModel "created" event.
     */
    public function created(LanguageModel $languageModel): void
    {
        $this->invalidateAiCaches('Language model created', $languageModel);
    }

    /**
     * Handle the LanguageModel "updated" event.
     */
    public function updated(LanguageModel $languageModel): void
    {
        // Check if relevant fields changed
        $relevantFields = ['is_active', 'provider_id', 'model_id'];
        
        if ($languageModel->wasChanged($relevantFields)) {
            $this->invalidateAiCaches('Language model updated', $languageModel);
        }
    }

    /**
     * Handle the LanguageModel "deleted" event.
     */
    public function deleted(LanguageModel $languageModel): void
    {
        $this->invalidateAiCaches('Language model deleted', $languageModel);
    }

    /**
     * Handle the LanguageModel "restored" event.
     */
    public function restored(LanguageModel $languageModel): void
    {
        $this->invalidateAiCaches('Language model restored', $languageModel);
    }

    /**
     * Handle the LanguageModel "force deleted" event.
     */
    public function forceDeleted(LanguageModel $languageModel): void
    {
        $this->invalidateAiCaches('Language model force deleted', $languageModel);
    }

    /**
     * Invalidate AI caches and log the action
     */
    private function invalidateAiCaches(string $reason, LanguageModel $model): void
    {
        try {
            // Get factory instance and clear caches
            $factory = app(AIProviderFactory::class);
            $factory->clearAllCaches();
        } catch (\Exception $e) {
            Log::error("Failed to clear AI caches after language model change", [
                'reason' => $reason,
                'model_id' => $model->model_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
