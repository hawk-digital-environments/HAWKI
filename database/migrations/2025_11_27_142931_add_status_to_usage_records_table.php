<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds status column to track the completion state of AI requests:
     * - NULL: Request is pending/in progress
     * - 'completed': Request finished successfully
     * - 'failed': Request failed with error
     * - 'cancelled': Request was cancelled by user
     */
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            // Only add if column doesn't exist
            if (!Schema::hasColumn('usage_records', 'status')) {
                $table->string('status', 20)->nullable()->after('server_tool_use');
                $table->index('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            if (Schema::hasColumn('usage_records', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
