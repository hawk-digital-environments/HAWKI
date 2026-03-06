<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Changes the 'type' column from ENUM to VARCHAR to support more specific tracking types:
     * - 'private': Regular 1:1 chat with AI
     * - 'group': Group chat with AI
     * - 'api': External API usage
     * - 'title': Title generation (system)
     * - 'improver': Prompt improvement (system)
     * - 'summarizer': Content summarization (system)
     */
    public function up(): void
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            // PostgreSQL: Drop the check constraint and change column type to VARCHAR
            DB::statement('ALTER TABLE usage_records DROP CONSTRAINT IF EXISTS usage_records_type_check;');
            DB::statement("ALTER TABLE usage_records ALTER COLUMN type TYPE VARCHAR(20);");
        } else {
            // MySQL: Change ENUM to VARCHAR
            DB::statement("ALTER TABLE `usage_records` MODIFY COLUMN `type` VARCHAR(20) NOT NULL;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            // PostgreSQL: Change back to VARCHAR with check constraint
            DB::statement("ALTER TABLE usage_records ALTER COLUMN type TYPE VARCHAR(20);");
            DB::statement(
                "ALTER TABLE usage_records
                 ADD CONSTRAINT usage_records_type_check
                 CHECK (type IN ('private', 'group', 'api', 'system'));"
            );
        } else {
            // MySQL: Change back to ENUM
            DB::statement("
                ALTER TABLE `usage_records`
                MODIFY COLUMN `type` ENUM('private', 'group', 'api', 'system')
            ");
        }
    }
};
