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
        Schema::create('system_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('model_type'); // z.B. 'default_model', 'title_generator', etc.
            $table->string('language', 5); // z.B. 'de', 'en'
            $table->text('prompt_text');
            $table->timestamps();
            
            // Unique-Constraint für model_type und language
            $table->unique(['model_type', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_prompts');
    }
};
