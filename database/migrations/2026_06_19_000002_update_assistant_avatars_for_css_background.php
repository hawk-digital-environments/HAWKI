<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->string('css_background')->default('');
        });

        Schema::table('assistants', function (Blueprint $table) {
            $table->unsignedBigInteger('avatar_id')->nullable()->change();
            $table->foreign('avatar_id')->references('id')->on('assistant_avatars')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropForeign(['avatar_id']);
        });

        Schema::table('assistants', function (Blueprint $table) {
            $table->string('avatar_id')->nullable()->change();
        });

        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->dropColumn('css_background');
        });
    }
};
