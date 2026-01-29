<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('private_user_data', static function (Blueprint $table) {
            $table->longText('keychain')->change();
        });
    }
    
    public function down(): void
    {
        // We can not simply revert this change, as data might be lost.
        // If you need to revert this migration, please ensure that no data will be lost.
        // Schema::table('private_user_data', function (Blueprint $table) {
        //     $table->text('keychain')->change();
        // });
    }
};
