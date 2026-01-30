<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove guidelines_content entries from app_localized_texts
        // Guidelines are now loaded dynamically from announcements (single source of truth)
        DB::table('app_localized_texts')
            ->where('content_key', 'guidelines_content')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to restore the data, as it's no longer used
        // Guidelines are loaded from announcements via fetchLatestPolicy()
    }
};
