<?php

namespace App\Services\Ai;

use App\Models\Records\UsageRecord;
use App\Services\Ai\Values\TokenUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Records and periodically summarises AI token-usage data.
 *
 * Each AI response that returns token counts is written as a raw
 * {@see UsageRecord} row via {@see submitUsageRecord()}. A separate scheduled
 * job calls {@see summarizeAndCleanup()} at month boundaries to aggregate the
 * previous month's rows and purge the originals.
 *
 * @deprecated This class will be replaced by a proper repository in a future release.
 */
class UsageAnalyzerService
{

    /**
     * Persists a token-usage record for the currently authenticated user.
     *
     * Does nothing when `$usage` is null, which happens when an agent response
     * did not include usage metadata (e.g. streamed responses before the final
     * chunk arrives).
     *
     * @param string      $type   Caller context: 'private', 'group', or 'api'.
     * @param int|null    $roomId The room this usage is associated with, or null for direct/API calls.
     */
    public function submitUsageRecord(?TokenUsage $usage, $type, $roomId = null)
    {
        if ($usage === null) {
            return;
        }

        $userId = Auth::user()->id;

        // Create a new record if none exists for today
        UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,

            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'model' => $usage->model->model_id,
            'type' => $type,
        ]);

    }

    /**
     * Aggregates the previous month's raw usage rows by user, room, type and model,
     * then deletes those raw rows.
     *
     * Daily totals are persisted transactionally before raw rows older than three months are deleted.
     */
    public function summarizeAndCleanup()
    {
        app(\App\Services\Admin\UsageStatistics::class)->summarize();
    }

}
