<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class UpdateEnumTypeOnUsageRecordsTable extends Migration
{
    public function up(): void
    {
        if (app('db')->getDriverName() === 'sqlite') {
            // SQLite does not enforce ENUM types (values are stored as TEXT),
            // so the 'api' value is already permissible without a schema change.
            return;
        }

        if (env('DB_CONNECTION') === 'pgsql') {
            // Check if the column uses a custom enum type or is just a varchar with a check constraint:
            // Query information_schema to get the column type
            $columnType = DB::table('information_schema.columns')
                ->where('table_name', 'usage_records')
                ->where('column_name', 'type')
                ->value('udt_name');

            if ('usage_type' === $columnType) {
                // If the column uses a custom enum type 'usage_type'
                DB::statement("ALTER TYPE usage_type ADD VALUE IF NOT EXISTS 'api';");
            } else {
                // Default Laravel enum - type is string/varchar with a check constraint
                // Drop the old check constraint (if any), then add the new one

                // Constraint names can be different if the table was renamed, but by default it's "usage_records_type_check"
                // You might want to check your postgres schema if unsure
                DB::statement('ALTER TABLE usage_records DROP CONSTRAINT IF EXISTS usage_records_type_check;');
                DB::statement(<<<'EOD'
ALTER TABLE usage_records
                     ADD CONSTRAINT usage_records_type_check
                     CHECK (type IN ('private', 'group', 'api'));
EOD);
            }
        } else {
            // This updates the 'type' column to include 'api'.
            DB::statement(<<<'EOD'

                ALTER TABLE `usage_records`
                MODIFY COLUMN `type` ENUM('private', 'group', 'api')

EOD);
        }
    }

    public function down(): void
    {
        if (app('db')->getDriverName() === 'sqlite') {
            return;
        }

        // This reverts the 'type' column to its previous state.
        DB::statement(<<<'EOD'

            ALTER TABLE `usage_records`
            MODIFY COLUMN `type` ENUM('private', 'group')

EOD);
    }
}
