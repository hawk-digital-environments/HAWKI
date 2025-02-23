<?php

namespace App\Services\AI;

use Carbon\Carbon;

use App\Models\User;
use App\Models\Room;
use App\Models\Records\UsageRecord;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UsageAnalyzerService
{

    public function submitUsageRecord($usage, $type, $model, $roomId = null) {
        $today = Carbon::today();
        $userId = Auth::user()->id;

        // Create a new record if none exists for today
        UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,

            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'model' => $model,
            'type' => $type,
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
