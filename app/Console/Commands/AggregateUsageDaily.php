<?php

namespace App\Console\Commands;

use App\Services\UsageAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateUsageDaily extends Command
{
    protected $signature = 'usage:aggregate-daily
                            {--date= : Specific date to aggregate (YYYY-MM-DD)}
                            {--from= : Start date for range (YYYY-MM-DD)}
                            {--to= : End date for range (YYYY-MM-DD)}
                            {--yesterday : Aggregate yesterday\'s data}
                            {--backfill= : Backfill last N days}
                            {--show-orphaned : Show records with NULL user_id}
                            {--delete-orphaned : Delete records with NULL user_id}';

    protected $description = 'Aggregate usage_records into daily summaries';

    public function handle(UsageAggregationService $service): int
    {
        // Handle orphaned records first if requested
        if ($this->option('show-orphaned')) {
            return $this->showOrphanedRecords();
        }
        
        if ($this->option('delete-orphaned')) {
            return $this->deleteOrphanedRecords();
        }
        
        $this->info('Starting usage aggregation...');

        if ($this->option('date')) {
            return $this->aggregateSingleDate($service, $this->option('date'));
        }
        
        if ($this->option('yesterday')) {
            return $this->aggregateSingleDate($service, Carbon::yesterday());
        }
        
        if ($this->option('from') && $this->option('to')) {
            return $this->aggregateDateRange($service, $this->option('from'), $this->option('to'));
        }
        
        if ($this->option('backfill')) {
            return $this->backfill($service, (int) $this->option('backfill'));
        }

        $this->error('Please specify one of: --date, --yesterday, --from/--to, --backfill, --show-orphaned, or --delete-orphaned');
        return Command::FAILURE;
    }

    private function aggregateSingleDate(UsageAggregationService $service, $date): int
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $this->info("Aggregating for: {$date->toDateString()}");
        
        $stats = $service->aggregateForDate($date);
        
        $this->table(
            ['Date', 'Combinations', 'Inserted', 'Updated', 'Errors'],
            [[$stats['date'], $stats['combinations'], $stats['inserted'], $stats['updated'], $stats['errors']]]
        );
        
        if ($stats['errors'] > 0) {
            $this->warn("Completed with {$stats['errors']} errors");
            return Command::FAILURE;
        }
        
        $this->info('Aggregation completed successfully!');
        return Command::SUCCESS;
    }

    private function aggregateDateRange(UsageAggregationService $service, string $from, string $to): int
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        
        $this->info("Aggregating range: {$start->toDateString()} to {$end->toDateString()}");
        
        $results = $service->aggregateForDateRange($start, $end);
        
        $rows = array_map(fn($stats) => [
            $stats['date'],
            $stats['combinations'],
            $stats['inserted'],
            $stats['updated'],
            $stats['errors'],
        ], $results);
        
        $this->table(['Date', 'Combinations', 'Inserted', 'Updated', 'Errors'], $rows);
        
        $totalErrors = array_sum(array_column($results, 'errors'));
        
        if ($totalErrors > 0) {
            $this->warn("Completed with {$totalErrors} total errors");
            return Command::FAILURE;
        }
        
        $this->info('Aggregation completed successfully!');
        return Command::SUCCESS;
    }

    private function backfill(UsageAggregationService $service, int $days): int
    {
        $this->info("Backfilling last {$days} days...");
        
        $results = $service->backfill($days);
        
        $rows = array_map(fn($stats) => [
            $stats['date'],
            $stats['combinations'],
            $stats['inserted'],
            $stats['updated'],
            $stats['errors'],
        ], $results);
        
        $this->table(['Date', 'Combinations', 'Inserted', 'Updated', 'Errors'], $rows);
        
        $totalErrors = array_sum(array_column($results, 'errors'));
        
        if ($totalErrors > 0) {
            $this->warn("Completed with {$totalErrors} total errors");
            return Command::FAILURE;
        }
        
        $this->info('Backfill completed successfully!');
        return Command::SUCCESS;
    }
    
    private function showOrphanedRecords(): int
    {
        $orphaned = \DB::table('usage_records')
            ->whereNull('user_id')
            ->select([
                \DB::raw('DATE(created_at) as date'),
                'api_provider',
                'model',
                \DB::raw('COUNT(*) as count'),
                \DB::raw('SUM(COALESCE(prompt_tokens, 0) + COALESCE(completion_tokens, 0)) as total_tokens'),
            ])
            ->groupBy(\DB::raw('DATE(created_at)'), 'api_provider', 'model')
            ->orderBy('date', 'desc')
            ->get();
        
        if ($orphaned->isEmpty()) {
            $this->info('No orphaned records found (user_id = NULL).');
            return Command::SUCCESS;
        }
        
        $this->warn("Found {$orphaned->count()} groups of orphaned records:");
        
        $rows = $orphaned->map(fn($row) => [
            $row->date,
            $row->api_provider,
            $row->model,
            $row->count,
            number_format($row->total_tokens),
        ])->toArray();
        
        $this->table(['Date', 'Provider', 'Model', 'Records', 'Total Tokens'], $rows);
        
        $totalRecords = $orphaned->sum('count');
        $totalTokens = $orphaned->sum('total_tokens');
        
        $this->warn("Total: {$totalRecords} orphaned records with " . number_format($totalTokens) . ' tokens');
        $this->line('');
        $this->info('Run with --delete-orphaned to remove these records.');
        
        return Command::SUCCESS;
    }
    
    private function deleteOrphanedRecords(): int
    {
        $count = \DB::table('usage_records')
            ->whereNull('user_id')
            ->count();
        
        if ($count === 0) {
            $this->info('No orphaned records found (user_id = NULL).');
            return Command::SUCCESS;
        }
        
        $this->warn("Found {$count} orphaned records.");
        
        if (!$this->confirm('Do you want to delete these records?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }
        
        $deleted = \DB::table('usage_records')
            ->whereNull('user_id')
            ->delete();
        
        $this->info("Deleted {$deleted} orphaned records.");
        
        return Command::SUCCESS;
    }
}
