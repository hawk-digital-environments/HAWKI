<?php
namespace App\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Records\UsageRecord;

class TopTokenUsers extends Command
{
    protected $signature = 'usage:top-users {--limit=10}{--model=gpt-4.1}';
    protected $description = 'Display top users with most prompt_tokens and completion_tokens this month';

    public function handle()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $limit = (int) $this->option('limit');
        $modelFilter = $this->option('model');

        $query = UsageRecord::select(
                'user_id',
                DB::raw('SUM(prompt_tokens) as total_prompt'),
                DB::raw('SUM(completion_tokens) as total_completion')
            )
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

        if ($modelFilter) {
            $query->where('model', $modelFilter);
        }

        $topUsers = $query->groupBy('user_id')
            ->orderByRaw('SUM(prompt_tokens + completion_tokens) DESC')
            ->limit($limit)
            ->get();

        $this->info("Top $limit users for " . now()->format('F Y') . ($modelFilter ? " using model [$modelFilter]" : "") . ":");


        // @Todo: The method will be deprecated after the admin panel is implemented.
        // We can create a dedicated data object here to contain total_prompt and total_completion, but it's unnecessary.
        // let's instead bypass php stan error for now.
        /** @var Collection<int, object{
         *     user_id: int,
         *     total_prompt: int,
         *     total_completion: int
         * }> $topUsers
         */
        foreach ($topUsers as $user) {
            $this->line("User ID: {$user->user_id}, Prompt Tokens: {$user->total_prompt}, Completion Tokens: {$user->total_completion}");
        }

        return 0;
    }

}

