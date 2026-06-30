<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_avatars', function (Blueprint $table) {
            if (Schema::hasColumn('assistant_avatars', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });
    }
};
