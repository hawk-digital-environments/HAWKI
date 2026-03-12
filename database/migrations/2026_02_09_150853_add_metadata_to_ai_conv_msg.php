<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_conv_msgs', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('completion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conv_msgs', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
