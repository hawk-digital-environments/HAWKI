<?php

namespace App\Services\Ai;

use App\Models\Records\UsageRecord;
use App\Services\Ai\Values\TokenUsage;
use App\Services\Ai\Values\UsageRecordContext;
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
     * Persists a token-usage record.
     *
     * Does nothing when `$usage` is null, which happens when an agent response
     * did not include usage metadata (e.g. streamed responses before the final
     * chunk arrives). The user falls back to the currently authenticated user
     * when the context does not carry one (queue context must set it explicitly).
     */
    public function submitUsageRecord(?TokenUsage $usage, UsageRecordContext $context): void
    {
        if ($usage === null) {
            return;
        }

        UsageRecord::create([
            'user_id' => $context->userId ?? Auth::id(),
            'room_id' => $context->roomId,

            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'model' => $usage->model->model_id,
            'type' => $context->type,

            'channel' => $context->channel,
            'user_agent' => $context->userAgent,
            'format_key' => $context->formatKey,
            'assistant_handle' => $context->assistantHandle,
        ]);

    }

    /**
     * Aggregates the previous month's raw usage rows by user, room, type, channel and
     * model, then deletes those raw rows.
     *
     * The summary storage step is currently a no-op placeholder — implementors should
     * persist the aggregated data before this method is put into production use.
     */
    public function summarizeAndCleanup()
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        $summaries = UsageRecord::selectRaw('user_id, room_id, type, channel, model, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->groupBy('user_id', 'room_id', 'type', 'channel', 'model')
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
