<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\System\Health\HealthChecker;
use Illuminate\Support\Facades\{Cache, DB, Queue, Schema};

class HealthMonitor
{
    public function read(): array
    {
        $health = app(HealthChecker::class)->deepCheck()->jsonSerialize();
        $rows = [];
        foreach ($health['results'] as $key => $value) $rows[] = ['id' => $key, 'name' => $key] + $value;
        foreach (['ai_models' => 'model', 'mcp_servers' => 'mcp'] as $table => $type) {
            foreach (DB::table($table)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->get() as $status) {
                $rows[] = ['id' => "$type-$status->status", 'name' => "$type: $status->status", 'status' => $status->status, 'message' => (string)$status->total];
            }
        }
        $queues = [];
        foreach (['default', 'mails', 'message_broadcast'] as $queue) {
            try { $queues[$queue] = Queue::size($queue); } catch (\Throwable) { $queues[$queue] = null; }
        }
        $heartbeat = Cache::get('admin.scheduler.last_seen');
        $rows[] = ['id' => 'scheduler', 'name' => 'scheduler', 'status' => $heartbeat && now()->diffInSeconds(\Carbon\CarbonImmutable::parse($heartbeat), true) < 180 ? 'ok' : 'unknown', 'message' => $heartbeat];
        return [
            'rows' => $rows, 'status' => $health['status'], 'queues' => $queues,
            // Failure payloads and exception traces may contain credentials and user data.
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->select(['uuid', 'connection', 'queue', 'failed_at'])->orderByDesc('failed_at')->limit(20)->get()->all() : [],
            'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'environment' => config('app.env'), 'debug' => config('app.debug'), 'config_cached' => app()->configurationIsCached()],
        ];
    }
}
