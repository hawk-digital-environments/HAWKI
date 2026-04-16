<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('announcements', static function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'target_roles')) {
                $table->json('target_roles')->nullable();
            }
            if (!Schema::hasColumn('announcements', 'is_published')) {
                $table->boolean('is_published')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'target_roles')) {
                $table->dropColumn('target_roles');
            }
            if (Schema::hasColumn('announcements', 'is_published')) {
                $table->dropColumn('is_published');
            }
        });
    }
};
