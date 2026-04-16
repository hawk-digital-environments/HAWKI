<?php

namespace App\Services;

use App\Models\User;
use App\Models\UsageUsersDaily;
use App\Models\Records\UsageRecord;
use App\Exceptions\QuotaExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Real-time quota management service.
 * 
 * Uses live aggregation in usage_users_daily table for quota checks.
 * This allows checking quotas BEFORE making AI requests.
 * 
 * Flow:
 * 1. estimateTokens() - Conservative token estimation before request
 * 2. checkQuota() - Check if user has enough quota (with row lock)
 * 3. recordUsage() - Record actual usage after AI response
 * 
 * Performance: ~50-100ms per check (DB transaction with row lock)
 * Good for: <1000 concurrent users
 * 
 * For higher scale: Consider Redis-based implementation (Phase 2)
 */
class QuotaService
{
    /**
     * Check if user has enough quota for estimated tokens.
     * 
     * Uses pessimistic locking to prevent race conditions.
     * 
     * @throws QuotaExceededException
     */
    public function checkQuota(
        User $user,
        string $apiProvider,
        string $model,
        int $estimatedTokens
    ): void {
        // Skip quota check for unlimited users (e.g., admins)
        if ($this->hasUnlimitedQuota($user)) {
            return;
        }

        DB::transaction(function () use ($user, $apiProvider, $model, $estimatedTokens) {
            // Get or create today's usage record with pessimistic lock
            $usage = UsageUsersDaily::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('date', today())
                ->where('api_provider', $apiProvider)
                ->where('model', $model)
                ->first();

            $currentTokens = $usage?->total_tokens ?? 0;
            $limit = $this->getUserLimit($user);

            // Check if adding estimated tokens would exceed limit
            if ($currentTokens + $estimatedTokens > $limit) {
                $remaining = max(0, $limit - $currentTokens);
                
                throw new QuotaExceededException(
                    "Daily token limit exceeded. Limit: {$limit}, Used: {$currentTokens}, Remaining: {$remaining}"
                );
            }
        });
    }

    /**
     * Record actual usage after AI request completes.
     * 
     * Updates usage_users_daily with actual token counts and costs.
     * Now supports tracking: success, failed, and cancelled requests.
     */
    public function recordUsage(UsageRecord $record): void
    {
        // Determine request type
        $isSuccess = $record->status === 'success';
        $isFailed = $record->status === 'failed';
        $isCancelled = $record->status === 'cancelled';
        
        // Try to find existing record
        $existing = UsageUsersDaily::where('user_id', $record->user_id)
            ->where('date', today())
            ->where('api_provider', $record->api_provider)
            ->where('model', $record->model)
            ->first();

        if ($existing) {
            // Update existing record (increment all values)
            $existing->increment('prompt_tokens', $record->prompt_tokens);
            $existing->increment('completion_tokens', $record->completion_tokens);
            $existing->increment('total_tokens', $record->total_tokens);
            $existing->increment('cache_read_input_tokens', $record->cache_read_input_tokens);
            $existing->increment('cache_creation_input_tokens', $record->cache_creation_input_tokens);
            $existing->increment('reasoning_tokens', $record->reasoning_tokens);
            $existing->increment('audio_input_tokens', $record->audio_input_tokens);
            $existing->increment('audio_output_tokens', $record->audio_output_tokens);
            $existing->increment('api_requests', 1);
            $existing->increment('successful_requests', $isSuccess ? 1 : 0);
            $existing->increment('failed_requests', $isFailed ? 1 : 0);
            $existing->increment('cancelled_requests', $isCancelled ? 1 : 0);
            
            // Merge server_tool_use JSON data
            if ($record->server_tool_use) {
                $existing->server_tool_use = $this->mergeServerToolUse(
                    $existing->server_tool_use,
                    $record->server_tool_use
                );
                $existing->save();
            }
        } else {
            // Create new record
            UsageUsersDaily::create([
                'user_id' => $record->user_id,
                'date' => today(),
                'api_provider' => $record->api_provider,
                'model' => $record->model,
                'prompt_tokens' => $record->prompt_tokens,
                'completion_tokens' => $record->completion_tokens,
                'total_tokens' => $record->total_tokens,
                'cache_read_input_tokens' => $record->cache_read_input_tokens,
                'cache_creation_input_tokens' => $record->cache_creation_input_tokens,
                'reasoning_tokens' => $record->reasoning_tokens,
                'audio_input_tokens' => $record->audio_input_tokens,
                'audio_output_tokens' => $record->audio_output_tokens,
                'server_tool_use' => $record->server_tool_use,
                'api_requests' => 1,
                'successful_requests' => $isSuccess ? 1 : 0,
                'failed_requests' => $isFailed ? 1 : 0,
                'cancelled_requests' => $isCancelled ? 1 : 0,
                'spend' => 0, // TODO: Calculate cost
            ]);
        }

        //Log::debug('Usage recorded', [
        //    'user_id' => $record->user_id,
        //    'model' => $record->model,
        //    'status' => $record->status,
        //    'tokens' => $record->total_tokens,
        //]);
    }
    
    /**
     * Merge two server_tool_use JSON objects.
     * Combines usage counts for the same tool types.
     *
     * @param array|null $existing Existing server_tool_use data
     * @param array|null $new New server_tool_use data to merge
     * @return array|null Merged server_tool_use data
     */
    private function mergeServerToolUse(?array $existing, ?array $new): ?array
    {
        if (empty($existing)) {
            return $new;
        }
        
        if (empty($new)) {
            return $existing;
        }
        
        $merged = $existing;
        
        // Merge counts for each tool type
        foreach ($new as $toolType => $count) {
            if (isset($merged[$toolType])) {
                $merged[$toolType] += $count;
            } else {
                $merged[$toolType] = $count;
            }
        }
        
        return $merged;
    }

    /**
     * Estimate tokens needed for a request.
     * 
     * Uses conservative estimation (1.5x prompt length).
     * Better to overestimate than to allow over-quota requests.
     */
    public function estimateTokens(array $messages): int
    {
        // Method 1: Character-based estimation
        // ~1 token = 4 characters (conservative for English)
        $promptChars = strlen(json_encode($messages));
        $promptTokens = (int) ceil($promptChars / 4);
        
        // Add 50% buffer for completion (conservative)
        $estimatedTotal = (int) ceil($promptTokens * 1.5);
        
        return max($estimatedTotal, 100); // Minimum 100 tokens
    }

    /**
     * Get current usage for user today.
     */
    public function getCurrentUsage(User $user, ?string $model = null): int
    {
        $query = UsageUsersDaily::where('user_id', $user->id)
            ->where('date', today());
        
        if ($model) {
            $query->where('model', $model);
        }
        
        return $query->sum('total_tokens');
    }

    /**
     * Get remaining quota for user today.
     */
    public function getRemainingQuota(User $user): int
    {
        $limit = $this->getUserLimit($user);
        $used = $this->getCurrentUsage($user);
        
        return max(0, $limit - $used);
    }

    /**
     * Check if user has unlimited quota.
     */
    private function hasUnlimitedQuota(User $user): bool
    {
        // TODO: Check user role/permissions
        // Example: return $user->hasRole('admin');
        return false;
    }

    /**
     * Get user's daily token limit.
     */
    private function getUserLimit(User $user): int
    {
        // TODO: Get from user settings or role
        // For now, default to 100k tokens per day
        return 100000;
    }
}
