<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEnumTypeOnUsageRecordsTable extends Migration
{
    public function up()
    {
        if(env('DB_CONNECTION') == 'pgsql') {
            // Check if the column uses a custom enum type or is just a varchar with a check constraint:
            // Query information_schema to get the column type
            $columnType = DB::table('information_schema.columns')
                ->where('table_name', 'usage_records')
                ->where('column_name', 'type')
                ->value('udt_name');

            if ($columnType === 'usage_type') {
                // If the column uses a custom enum type 'usage_type'
                DB::statement("ALTER TYPE usage_type ADD VALUE IF NOT EXISTS 'api';");
            } else {
                // Default Laravel enum - type is string/varchar with a check constraint
                // Drop the old check constraint (if any), then add the new one

                // Constraint names can be different if the table was renamed, but by default it's "usage_records_type_check"
                // You might want to check your postgres schema if unsure
                DB::statement('ALTER TABLE usage_records DROP CONSTRAINT IF EXISTS usage_records_type_check;');
                DB::statement(
                    "ALTER TABLE usage_records
                     ADD CONSTRAINT usage_records_type_check
                     CHECK (type IN ('private', 'group', 'api'));"
                );
            }
        }
        else{
            // This updates the 'type' column to include 'api'.
            DB::statement("
                ALTER TABLE `usage_records`
                MODIFY COLUMN `type` ENUM('private', 'group', 'api')
            ");
        }
    }

    public function down()
    {
        // This reverts the 'type' column to its previous state.
        DB::statement("
            ALTER TABLE `usage_records`
            MODIFY COLUMN `type` ENUM('private', 'group')
        ");
    }
}
