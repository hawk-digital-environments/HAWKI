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
        Schema::create('app_system_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_type'); // z.B. 'default_model', 'title_generation', etc.
            $table->string('language', 10); // z.B. 'de_DE', 'en_US', erhöht von 5 auf 10 Zeichen
            $table->text('prompt_text');
            $table->timestamps();
            
            // Unique-Constraint für model_type und language
            $table->unique(['prompt_type', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_system_prompts');
    }
};
