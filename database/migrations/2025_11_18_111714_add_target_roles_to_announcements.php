<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('announcements', static function (Blueprint $table) {
            $table->json('target_roles')->nullable();
            $table->boolean('is_published')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('target_roles');
            $table->dropColumn('is_published');
        });
    }
};
