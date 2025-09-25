<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the new ai_models table (renamed from language_models).
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('system_id')->unique(); // Unique system identifier
            $table->string('model_id'); // ID from the provider (e.g., "gpt-4o") - no longer unique to allow multiple providers
            $table->string('label'); // Display name (e.g., "OpenAI GPT-4o")
            $table->foreignId('provider_id')->constrained('api_providers')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true); // Whether to show in user UI
            $table->integer('display_order')->default(0); // For custom ordering
            $table->json('information')->nullable(); // For any additional model-specific settings
            $table->json('settings')->nullable(); // For any additional model-specific settings
            $table->timestamps();

            // Composite index for efficient model_id + provider_id queries
            $table->index(['model_id', 'provider_id'], 'ai_models_model_provider_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
