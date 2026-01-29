<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('has_thread')->default(false)->after('thread_id');
        });
    }
    
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('has_thread');
        });
    }
};
