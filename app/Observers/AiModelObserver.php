<?php

namespace App\Observers;

use App\Models\AiModel;
use App\Services\AI\Config\AiConfigService;

class AiModelObserver
{
    /**
     * Handle the AiModel "updated" event.
     * Clear cache when model active/visible status changes
     *
     * @param AiModel $aiModel
     * @return void
     */
    public function updated(AiModel $aiModel): void
    {
        // Check if is_active or is_visible fields were changed
        if ($aiModel->wasChanged(['is_active', 'is_visible', 'display_order'])) {
            $this->clearAiConfigCache();
        }
    }

    /**
     * Handle the AiModel "created" event.
     * Clear cache when new model is created
     *
     * @param AiModel $aiModel
     * @return void
     */
    public function created(AiModel $aiModel): void
    {
        $this->clearAiConfigCache();
    }

    /**
     * Handle the AiModel "deleted" event.
     * Clear cache when model is deleted
     *
     * @param AiModel $aiModel
     * @return void
     */
    public function deleted(AiModel $aiModel): void
    {
        $this->clearAiConfigCache();
    }

    /**
     * Clear the AI configuration cache
     *
     * @return void
     */
    private function clearAiConfigCache(): void
    {
        try {
            $aiConfigService = app(AiConfigService::class);
            $aiConfigService->clearCache();
            
            // Also clear general application cache to ensure UI updates
            \Illuminate\Support\Facades\Cache::flush();
            
            //\Log::info('AI model cache cleared due to model changes');
        } catch (\Exception $e) {
            \Log::error('Failed to clear AI config cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}