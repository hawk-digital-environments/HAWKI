<?php

namespace App\Services\AI;

use App\Models\Records\UsageRecord;
use App\Services\AI\Value\TokenUsage;
use App\Services\QuotaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UsageAnalyzerService
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}
    
    /**
     * Normalize server_tool_use keys across providers.
     * 
     * Different providers use different naming conventions:
     * - OpenAI: "web_search_requests"
     * - Anthropic: "web_search"
     * - Google: "grounding_queries", "tool_use_tokens"
     * 
     * We normalize to a consistent format:
     * - "web_search" for web/grounding searches
     * - "code_execution" for code execution
     * - "image_generation" for image generation
     * - "tool_use_tokens" stays as-is for token counts
     */
    private function normalizeServerToolUse(?array $serverToolUse): ?array
    {
        if (empty($serverToolUse)) {
            return null;
        }
        
        $normalized = [];
        
        foreach ($serverToolUse as $key => $value) {
            $normalizedKey = match($key) {
                // Web search normalization
                'web_search_requests', 'grounding_queries' => 'web_search',
                // Keep other keys as-is (including tool_use_tokens)
                default => $key
            };
            
            // If key already exists, add to it (shouldn't happen, but just in case)
            if (isset($normalized[$normalizedKey])) {
                $normalized[$normalizedKey] += $value;
            } else {
                $normalized[$normalizedKey] = $value;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Get unique provider name from model identifier.
     * 
     * This is the ONLY way to get provider - always uses AiService to load the model.
     * Since AiConfigService now uses unique_name as array key, getId() returns unique_name directly.
     * 
     * @param string $modelId Model identifier (e.g., "gpt-4o-mini", "gemma3:27b")
     * @return string Unique provider name (e.g., "openai-usa", "ki-at-jlu")
     */
    private function getProviderFromModel(string $modelId): string
    {
        try {
            // Load the actual model from AiService to get correct provider
            $aiService = app(\App\Services\AI\AiService::class);
            $availableModels = $aiService->getAvailableModels();
            
            // Get the model from the collection
            $model = $availableModels->models->getModel($modelId);
            
            if ($model === null) {
                \Log::warning('Model not found in AiService', [
                    'model' => $modelId
                ]);
                return 'unknown';
            }
            
            // getId() now returns unique_name directly (since AiConfigService uses it as key)
            return $model->getProvider()->getConfig()->getId();
        } catch (\Exception $e) {
            \Log::error('Failed to determine provider from model', [
                'model' => $modelId,
                'error' => $e->getMessage()
            ]);
            return 'unknown';
        }
    }
    
    /**
     * Create an initial usage record when a request starts
     * 
     * @param string $type Request type ('private', 'group', 'api', etc.)
     * @param string|null $model Model identifier if known
     * @param string|null $apiProvider Provider identifier if known
     * @param int|null $roomId Room ID if applicable
     * @return UsageRecord The created record (can be updated later)
     */
    public function createPendingRecord(string $type, ?string $model = null, ?string $apiProvider = null, ?int $roomId = null): UsageRecord
    {
        $userId = Auth::user()->id;
        
        // If apiProvider not provided, get it from the model via AiService
        if ($apiProvider === null && $model !== null) {
            $apiProvider = $this->getProviderFromModel($model);
        }
        
        return UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'type' => $type,
            'api_provider' => $apiProvider,
            'model' => $model ?? 'unknown',
            'status' => null, // NULL until request completes/fails/cancels
        ]);
    }
    
    /**
     * Update an existing usage record with final usage data
     * 
     * @param UsageRecord $record The record to update
     * @param TokenUsage|null $usage Usage data from completed request
     * @param string $status Final status: 'success', 'failed', 'cancelled'
     * @return void
     */
    public function updateRecord(UsageRecord $record, ?TokenUsage $usage, string $status = 'success'): void
    {
        $updates = [
            'status' => $status,
        ];
        
        if ($usage !== null) {
            $updates = array_merge($updates, [
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
                'cache_read_input_tokens' => $usage->cacheReadInputTokens,
                'cache_creation_input_tokens' => $usage->cacheCreationInputTokens,
                'reasoning_tokens' => $usage->reasoningTokens,
                'audio_input_tokens' => $usage->audioInputTokens,
                'audio_output_tokens' => $usage->audioOutputTokens,
                'server_tool_use' => $this->normalizeServerToolUse($usage->serverToolUse),
                'model' => $usage->model->getId(),
                'api_provider' => $this->getProviderFromModel($usage->model->getId()),
            ]);
        }
        
        $record->update($updates);
        
        // Record usage in daily aggregation for all statuses (success, failed, cancelled)
        // This ensures all requests are counted in api_requests
        try {
            $this->quotaService->recordUsage($record);
        } catch (\Exception $e) {
            \Log::error('Failed to record usage in daily aggregation', [
                'record_id' => $record->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Submit a usage record with specific type tracking
     *
     * @param TokenUsage|null $usage
     * @param string $type Supported types: 'private', 'group', 'api', 'title', 'improver', 'summarizer'
     * @param int|null $roomId
     * @param string $status Request status: 'success', 'failed', 'cancelled'
     * @return void
     */
    public function submitUsageRecord(?TokenUsage $usage, string $type, ?int $roomId = null, string $status = 'success'): void
    {
        if ($usage === null && $status === 'success') {
            // Only skip if no usage AND status is success (failed/cancelled requests should always be tracked)
            return;
        }

        $userId = Auth::user()->id;
        
        // Get provider unique_name from model (now unified approach)
        $apiProvider = null;
        if ($usage !== null) {
            $apiProvider = $this->getProviderFromModel($usage->model->getId());
        }

        // Create a new record
        $record = UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,
            'prompt_tokens' => $usage?->promptTokens ?? 0,
            'completion_tokens' => $usage?->completionTokens ?? 0,
            'cache_read_input_tokens' => $usage?->cacheReadInputTokens ?? 0,
            'cache_creation_input_tokens' => $usage?->cacheCreationInputTokens ?? 0,
            'reasoning_tokens' => $usage?->reasoningTokens ?? 0,
            'audio_input_tokens' => $usage?->audioInputTokens ?? 0,
            'audio_output_tokens' => $usage?->audioOutputTokens ?? 0,
            'server_tool_use' => $this->normalizeServerToolUse($usage?->serverToolUse),
            'type' => $type,
            'api_provider' => $apiProvider,
            'model' => $usage?->model->getId() ?? 'unknown',
            'status' => $status,
        ]);
        
        // Record usage in daily aggregation (live update for quota tracking)
        try {
            $this->quotaService->recordUsage($record);
        } catch (\Exception $e) {
            \Log::error('Failed to record usage in daily aggregation', [
                'record_id' => $record->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Record a failed/errored request
     * 
     * @param string $type Request type ('private', 'group', 'api', etc.)
     * @param string|null $model Model identifier if known
     * @param string|null $apiProvider Provider identifier if known
     * @param int|null $roomId Room ID if applicable
     * @param string $status Status: 'failed' (server error) or 'cancelled' (user abort)
     * @return void
     */
    public function recordError(string $type, ?string $model = null, ?string $apiProvider = null, ?int $roomId = null, string $status = 'failed'): void
    {
        $userId = Auth::user()->id;
        
        UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'type' => $type,
            'api_provider' => $apiProvider,
            'model' => $model ?? 'unknown',
            'status' => $status,
        ]);
    }

    public function summarizeAndCleanup()
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Updated summary logic to include the 'model' column
        $summaries = UsageRecord::selectRaw('user_id, room_id, type, model, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->groupBy('user_id', 'room_id', 'type', 'model')
            ->get();

        foreach ($summaries as $summary) {
            // Store summaries in another table, save to a file, or perform another action
        }

        // Clean up old records
        UsageRecord::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->delete();
    }

}
