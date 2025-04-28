<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEnumTypeOnUsageRecordsTable extends Migration
{
    public function up()
    {
        // This updates the 'type' column to include 'api'.
        DB::statement("
            ALTER TABLE `usage_records`
            MODIFY COLUMN `type` ENUM('private', 'group', 'api')
        ");
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