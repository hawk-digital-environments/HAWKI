<?php

namespace App\Services;

use App\Models\Records\UsageRecord;
use App\Models\UsageUsersDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsageAggregationService
{
    public function aggregateForDate($date = null): array
    {
        $date = $this->parseDate($date);
        
        Log::info('Starting usage aggregation', ['date' => $date->toDateString()]);
        
        $aggregated = $this->getAggregatedData($date);
        
        $stats = [
            'date' => $date->toDateString(),
            'combinations' => count($aggregated),
            'inserted' => 0,
            'updated' => 0,
            'errors' => 0,
        ];
        
        foreach ($aggregated as $row) {
            try {
                $existed = UsageUsersDaily::where('user_id', $row->user_id)
                    ->where('date', $row->date)
                    ->where('api_provider', $row->api_provider)
                    ->where('model', $row->model)
                    ->exists();
                
                UsageUsersDaily::updateOrCreate(
                    [
                        'user_id' => $row->user_id,
                        'date' => $row->date,
                        'api_provider' => $row->api_provider,
                        'model' => $row->model,
                    ],
                    [
                        'prompt_tokens' => $row->prompt_tokens,
                        'completion_tokens' => $row->completion_tokens,
                        'total_tokens' => $row->total_tokens,
                        'cache_read_input_tokens' => $row->cache_read_input_tokens,
                        'cache_creation_input_tokens' => $row->cache_creation_input_tokens,
                        'reasoning_tokens' => $row->reasoning_tokens,
                        'audio_input_tokens' => $row->audio_input_tokens,
                        'audio_output_tokens' => $row->audio_output_tokens,
                        'server_tool_use' => $this->mergeServerToolUse($row),
                        'api_requests' => $row->api_requests,
                        'successful_requests' => $row->successful_requests,
                        'failed_requests' => $row->failed_requests,
                        'cancelled_requests' => $row->cancelled_requests,
                        'spend' => 0,
                        'updated_at' => now(),
                    ]
                );
                
                $existed ? $stats['updated']++ : $stats['inserted']++;
                
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Aggregation error', [
                    'user_id' => $row->user_id,
                    'date' => $row->date,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info('Aggregation completed', $stats);
        
        return $stats;
    }
    
    public function aggregateForDateRange($startDate, $endDate): array
    {
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);
        
        $results = [];
        
        for ($date = $start; $date->lte($end); $date->addDay()) {
            $results[] = $this->aggregateForDate($date->copy());
        }
        
        return $results;
    }
    
    public function backfill(int $days = 7): array
    {
        $endDate = Carbon::yesterday();
        $startDate = $endDate->copy()->subDays($days - 1);
        
        return $this->aggregateForDateRange($startDate, $endDate);
    }
    
    protected function getAggregatedData(Carbon $date)
    {
        return DB::table('usage_records')
            ->whereDate('created_at', $date)
            ->whereNotNull('user_id')
            ->select([
                'user_id',
                DB::raw('DATE(created_at) as date'),
                'api_provider',
                'model',
                DB::raw('COUNT(*) as api_requests'),
                DB::raw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_requests'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_requests'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_requests'),
                DB::raw('SUM(COALESCE(prompt_tokens, 0)) as prompt_tokens'),
                DB::raw('SUM(COALESCE(completion_tokens, 0)) as completion_tokens'),
                DB::raw('SUM(COALESCE(prompt_tokens, 0) + COALESCE(completion_tokens, 0)) as total_tokens'),
                DB::raw('SUM(COALESCE(cache_read_input_tokens, 0)) as cache_read_input_tokens'),
                DB::raw('SUM(COALESCE(cache_creation_input_tokens, 0)) as cache_creation_input_tokens'),
                DB::raw('SUM(COALESCE(reasoning_tokens, 0)) as reasoning_tokens'),
                DB::raw('SUM(COALESCE(audio_input_tokens, 0)) as audio_input_tokens'),
                DB::raw('SUM(COALESCE(audio_output_tokens, 0)) as audio_output_tokens'),
                DB::raw('GROUP_CONCAT(server_tool_use) as server_tool_use_json'),
            ])
            ->groupBy('user_id', DB::raw('DATE(created_at)'), 'api_provider', 'model')
            ->get();
    }
    
    protected function mergeServerToolUse($row): ?array
    {
        if (empty($row->server_tool_use_json)) {
            return null;
        }
        
        $merged = [];
        $jsonStrings = explode(',', $row->server_tool_use_json);
        
        foreach ($jsonStrings as $jsonString) {
            if (empty($jsonString) || $jsonString === 'null') {
                continue;
            }
            
            $data = json_decode($jsonString, true);
            if (!is_array($data)) {
                continue;
            }
            
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $merged[$key] = ($merged[$key] ?? 0) + $value;
                }
            }
        }
        
        return empty($merged) ? null : $merged;
    }
    
    protected function parseDate($date = null): Carbon
    {
        if ($date === null) {
            return Carbon::yesterday();
        }
        
        if ($date instanceof Carbon) {
            return $date;
        }
        
        return Carbon::parse($date);
    }
}
