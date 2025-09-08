<?php

namespace App\Services\AI\Providers;

use App\Models\LanguageModel;
use Illuminate\Support\Facades\Log;

trait WebSearchTrait
{
    /**
     * Standardized method to check if a model supports Web Search functionality
     *
     * This trait provides a unified way to check web search capabilities across all providers.
     * It handles both legacy formats and ensures consistent behavior.
     */
    public function modelSupportsSearch(string $modelId): bool
    {
        try {
            // Find model by either system_id (UUID) or model_id (provider-specific ID)
            $model = LanguageModel::where('system_id', $modelId)
                ->orWhere('model_id', $modelId)
                ->where('is_active', true)
                ->first();

            if (! $model) {
                Log::warning("Model not found for search check: $modelId");

                return false;
            }

            // Parse settings - handle both array and object formats
            $settings = $this->parseModelSettings($model->settings);

            // Check for web_search capability using standardized field names
            return $this->checkWebSearchInSettings($settings);

        } catch (\Exception $e) {
            Log::warning('Failed to get model details for search check: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Parse model settings from database, handling multiple formats
     */
    private function parseModelSettings($rawSettings): ?array
    {
        if (empty($rawSettings)) {
            return null;
        }

        // Convert to array if it's a JSON string
        $settings = is_array($rawSettings) ?
                   $rawSettings :
                   json_decode($rawSettings, true);

        if (! is_array($settings)) {
            return null;
        }

        return $settings;
    }

    /**
     * Check for web search capability in parsed settings
     * Handles both legacy and current formats consistently
     */
    private function checkWebSearchInSettings(?array $settings): bool
    {
        if (! is_array($settings)) {
            return false;
        }

        // Format 1: Array with index 0 (newer format)
        // [{"web_search": true}] or [{"search_tool": true}]
        if (isset($settings[0]) && is_array($settings[0])) {
            $firstSetting = $settings[0];

            // Check for web_search (primary field)
            if (isset($firstSetting['web_search'])) {
                return (bool) $firstSetting['web_search'];
            }

            // Check for search_tool (legacy field)
            if (isset($firstSetting['search_tool'])) {
                return (bool) $firstSetting['search_tool'];
            }
        }

        // Format 2: Direct object (legacy format)
        // {"web_search": true} or {"search_tool": true}

        // Check for web_search (primary field)
        if (isset($settings['web_search'])) {
            return (bool) $settings['web_search'];
        }

        // Check for search_tool (legacy field)
        if (isset($settings['search_tool'])) {
            return (bool) $settings['search_tool'];
        }

        return false;
    }

    /**
     * Get the appropriate web search tool configuration for this provider
     * Override in each provider to return provider-specific tool config
     */
    public function getWebSearchToolConfig(): array
    {
        // Default configuration - should be overridden by each provider
        return [
            ['type' => 'web_search'],
        ];
    }

    /**
     * Add web search tools to payload if model supports it
     */
    protected function addWebSearchTools(array &$payload, string $modelId, array $rawPayload): void
    {
        $supportsSearch = $this->modelSupportsSearch($modelId);

        if ($supportsSearch) {
            $payload['tools'] = $rawPayload['tools'] ?? $this->getWebSearchToolConfig();
        } elseif (isset($rawPayload['tools'])) {
            // Only add tools if model supports them and they were explicitly provided
            $payload['tools'] = $rawPayload['tools'];
        }
    }
}
