<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Repositions the status column to appear between model and prompt_tokens
     * for better logical ordering in the database schema.
     */
    public function up(): void
    {
        // MySQL/MariaDB specific - reorder column position
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `status` VARCHAR(20) NULL AFTER `model`');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Move status back to its previous position (after server_tool_use)
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `status` VARCHAR(20) NULL AFTER `server_tool_use`');
        }
    }
};
