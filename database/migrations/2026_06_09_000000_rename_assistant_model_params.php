<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            if (Schema::hasColumn('assistants', 'model_length')) {
                $table->renameColumn('model_length', 'max_tokens');
            }
            if (Schema::hasColumn('assistants', 'model_temp')) {
                $table->renameColumn('model_temp', 'temp');
            }
            if (Schema::hasColumn('assistants', 'model_top_p')) {
                $table->renameColumn('model_top_p', 'top_p');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            if (Schema::hasColumn('assistants', 'max_tokens')) {
                $table->renameColumn('max_tokens', 'model_length');
            }
            if (Schema::hasColumn('assistants', 'temp')) {
                $table->renameColumn('temp', 'model_temp');
            }
            if (Schema::hasColumn('assistants', 'top_p')) {
                $table->renameColumn('top_p', 'model_top_p');
            }
        });
    }
};
