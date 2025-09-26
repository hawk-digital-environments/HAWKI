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
        Schema::create('ai_assistants_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_type'); // Keys that match JSON translation keys (e.g. 'Name_Prompt', 'Default_Prompt', etc.)
            $table->string('language', 10); // e.g. 'de_DE', 'en_US'
            $table->text('prompt_text');
            $table->timestamps();

            // Unique-Constraint für prompt_type und language
            $table->unique(['prompt_type', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistants_prompts');
    }
};
