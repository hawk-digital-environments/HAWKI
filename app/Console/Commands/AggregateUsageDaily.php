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
                            {--backfill= : Backfill last N days}';

    protected $description = 'Aggregate usage_records into daily summaries';

    public function handle(UsageAggregationService $service): int
    {
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

        $this->error('Please specify one of: --date, --yesterday, --from/--to, or --backfill');
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
}
