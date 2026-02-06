<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Get all distinct thread_ids that are not null, and set has_thread to true for all messages with those thread_ids
        $threadIds = Message::whereNotNull('thread_id')->distinct()->pluck('thread_id');
        if ($threadIds->isEmpty()) {
            return;
        }
        Message::whereIn('id', $threadIds)->update(['has_thread' => true]);
    }
    
    public function down(): void
    {
        Message::query()->update(['has_thread' => false]);
    }
};
