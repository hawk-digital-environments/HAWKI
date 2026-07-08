<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->renameColumn('css_background', 'icon_css');
        });

        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->string('icon_css', 1000)->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->renameColumn('icon_css', 'css_background');
        });

        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->string('css_background', 255)->default('')->change();
        });
    }
};
