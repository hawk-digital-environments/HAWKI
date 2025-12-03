<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            // Check if the column uses a custom enum type or is just a varchar with a check constraint
            $columnType = DB::table('information_schema.columns')
                ->where('table_name', 'usage_records')
                ->where('column_name', 'type')
                ->value('udt_name');

            if ($columnType === 'usage_type') {
                // If the column uses a custom enum type 'usage_type'
                DB::statement("ALTER TYPE usage_type ADD VALUE IF NOT EXISTS 'system';");
            } else {
                // Default Laravel enum - type is string/varchar with a check constraint
                // Drop the old check constraint, then add the new one
                DB::statement('ALTER TABLE usage_records DROP CONSTRAINT IF EXISTS usage_records_type_check;');
                DB::statement(
                    "ALTER TABLE usage_records
                     ADD CONSTRAINT usage_records_type_check
                     CHECK (type IN ('private', 'group', 'api', 'system'));"
                );
            }
        } else {
            // MySQL: Update the ENUM to include 'system'
            DB::statement("
                ALTER TABLE `usage_records`
                MODIFY COLUMN `type` ENUM('private', 'group', 'api', 'system')
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            // PostgreSQL doesn't support removing enum values easily
            // We need to recreate the constraint without 'system'
            DB::statement('ALTER TABLE usage_records DROP CONSTRAINT IF EXISTS usage_records_type_check;');
            DB::statement(
                "ALTER TABLE usage_records
                 ADD CONSTRAINT usage_records_type_check
                 CHECK (type IN ('private', 'group', 'api'));"
            );
        } else {
            // MySQL: Revert the ENUM to exclude 'system'
            DB::statement("
                ALTER TABLE `usage_records`
                MODIFY COLUMN `type` ENUM('private', 'group', 'api')
            ");
        }
    }
};
