<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->string('avatar_id')->nullable()->after('top_p');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn('avatar_id');
        });
    }
};
