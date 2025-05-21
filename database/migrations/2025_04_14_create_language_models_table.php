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
        Schema::create('language_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique(); // Unique ID from the provider (e.g., "gpt-4o")
            $table->string('label'); // Display name (e.g., "OpenAI GPT-4o")
            $table->foreignId('provider_id')->constrained('provider_settings')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('streamable')->default(true);
            $table->boolean('is_visible')->default(true); // Whether to show in user UI
            $table->integer('display_order')->default(0); // For custom ordering
            $table->json('information')->nullable(); // For any additional model-specific settings
            $table->json('settings')->nullable(); // For any additional model-specific settings
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_models');
    }
};
