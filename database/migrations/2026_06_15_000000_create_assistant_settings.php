<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('ui_type');
            $table->json('ui_options')->nullable();
            $table->text('prompt_template')->nullable();
            $table->json('default_value')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_setting_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->foreignId('setting_id')
                ->constrained('assistant_settings')
                ->cascadeOnDelete();
            $table->json('value');
            $table->timestamps();

            $table->unique(['assistant_id', 'setting_id']);
        });

        if (Schema::hasColumn('assistants', 'formality')) {
            Schema::table('assistants', function (Blueprint $table) {
                $table->dropColumn('formality');
            });
        }

        if (Schema::hasColumn('assistants', 'language_id')) {
            Schema::table('assistants', function (Blueprint $table) {
                $table->dropForeign(['language_id']);
                $table->dropColumn('language_id');
            });
        }

        Schema::dropIfExists('languages');
    }

    public function down(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('text')->unique();
            $table->timestamps();
        });

        Schema::table('assistants', function (Blueprint $table) {
            $table->foreignId('language_id')
                ->nullable()
                ->constrained('languages')
                ->nullOnDelete();
            $table->string('formality')->default('neutral');
        });

        Schema::dropIfExists('assistant_setting_values');
        Schema::dropIfExists('assistant_settings');
    }
};
