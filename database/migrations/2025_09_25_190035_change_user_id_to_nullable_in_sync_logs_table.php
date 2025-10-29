<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->index()->change();
        });
    }
    
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->integer('user_id')->index(false)->change();
        });
    }
};
