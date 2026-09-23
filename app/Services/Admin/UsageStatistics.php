<?php
declare(strict_types=1);

namespace App\Services\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UsageStatistics
{
    /** Persist and remove exactly the rows locked by this transaction. Retain three full months. */
    public function summarize(): void
    {
        $cutoff = CarbonImmutable::now()->startOfMonth()->subMonths(3);
        do {
            $count = DB::transaction(function () use ($cutoff) {
                $rows = DB::table('usage_records')->where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->lockForUpdate()->get();
                foreach ($rows->groupBy(fn($row) => json_encode([substr($row->created_at, 0, 10), $row->user_id, $row->model, $row->type])) as $group) {
                    $first = $group->first();
                    $key = ['day' => substr($first->created_at, 0, 10), 'user_id' => $first->user_id, 'model' => $first->model, 'type' => $first->type];
                    DB::table('usage_daily_totals')->insertOrIgnore($key + ['requests' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0]);
                    DB::table('usage_daily_totals')->where($key)->incrementEach([
                        'requests' => $group->count(), 'prompt_tokens' => $group->sum('prompt_tokens'), 'completion_tokens' => $group->sum('completion_tokens'),
                    ]);
                }
                DB::table('usage_records')->whereIn('id', $rows->pluck('id'))->delete();
                return $rows->count();
            }, 3);
        } while ($count === 1000);
    }

    public function read(array $filters): array
    {
        $from = $filters['from'] ?? now()->subDays(29)->toDateString();
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString())->addDay()->toDateString();
        $raw = DB::table('usage_records')->selectRaw('DATE(created_at) as day, user_id, model, type, 1 as requests, prompt_tokens, completion_tokens')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $stored = DB::table('usage_daily_totals')->select(['day', 'user_id', 'model', 'type', 'requests', 'prompt_tokens', 'completion_tokens'])
            ->where('day', '>=', $from)->where('day', '<', $to);
        $query = DB::query()->fromSub($raw->unionAll($stored), 'usage');
        if (!empty($filters['model'])) $query->where('model', $filters['model']);
        if (!empty($filters['user'])) $query->where('user_id', $filters['user']);
        $totals = (clone $query)->selectRaw('COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(prompt_tokens), 0) as prompt_tokens, COALESCE(SUM(completion_tokens), 0) as completion_tokens, COUNT(DISTINCT user_id) as active_users')->first();
        $group = $filters['group_by'] ?? 'day';
        $expression = match ($group) {
            'month' => 'SUBSTR(day, 1, 7)', 'user' => 'user_id', 'provider' => "COALESCE(ai_providers.name, 'Unknown')", 'model' => 'usage.model', 'type' => 'type', default => 'day',
        };
        if ($group === 'provider') $query->leftJoin('ai_models', 'ai_models.model_id', '=', 'usage.model')->leftJoin('ai_providers', 'ai_providers.id', '=', 'ai_models.provider_id');
        $series = $query->selectRaw("$expression as label, SUM(requests) as requests, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens")
            ->groupByRaw($expression)->orderBy('label')->get()->map(fn($row) => ['id' => (string)$row->label] + (array)$row)->all();
        return ['rows' => $series, 'totals' => (array)$totals, 'from' => $from, 'to' => substr($to, 0, 10)];
    }
}
