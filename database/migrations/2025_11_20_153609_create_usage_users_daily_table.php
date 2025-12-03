<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the daily usage aggregation table for user-level statistics.
     * 
     * This table aggregates usage_records by user, date, model, and provider.
     * One row per user × date × model × provider combination.
     * 
     * Similar to LiteLLM's DailyUserSpend table but with HAWKI-specific extensions:
     * - Room/Type tracking (HAWKI-specific)
     * - Price snapshots for historical accuracy
     * - Audio tokens (not in LiteLLM)
     * - Server tool use aggregation
     * 
     * Example: User 42 used gpt-4o-mini on 2025-11-20 → 1 row with aggregated stats
     */
    public function up(): void
    {
        Schema::create('usage_users_daily', function (Blueprint $table) {
            $table->id();
            
            // Grouping Keys (unique constraint)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('api_provider'); // OpenAI, Google, GWDG, Ollama
            $table->string('model');        // gpt-4o-mini, gemini-pro, etc.
            
            // Request Tracking
            $table->unsignedBigInteger('api_requests')->default(0);
            $table->unsignedBigInteger('successful_requests')->default(0);
            $table->unsignedBigInteger('failed_requests')->default(0);
            $table->unsignedBigInteger('cancelled_requests')->default(0);
            
            // Standard Token Metrics
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            
            // Cache Tokens (Prompt Caching)
            $table->unsignedBigInteger('cache_read_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_creation_input_tokens')->default(0);
            
            // Reasoning Tokens (o1, o3-mini, Claude Extended Thinking)
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            
            // Audio Tokens (GPT-4o Audio, multimodal)
            $table->unsignedBigInteger('audio_input_tokens')->default(0);
            $table->unsignedBigInteger('audio_output_tokens')->default(0);
            
            // Server Tool Use (aggregated from JSON fields)
            // Example: {"web_search": 15, "code_execution": 8}
            $table->json('server_tool_use')->nullable();
            
            // Cost Tracking with Price Snapshots
            $table->decimal('spend', 10, 4)->default(0);
            
            // Price Snapshots (for historical accuracy when prices change)
            $table->decimal('input_token_price_per_1k', 10, 6)->nullable();
            $table->decimal('cache_read_price_per_1k', 10, 6)->nullable();
            $table->decimal('cache_write_price_per_1k', 10, 6)->nullable();
            $table->decimal('output_token_price_per_1k', 10, 6)->nullable();
            $table->decimal('reasoning_token_price_per_1k', 10, 6)->nullable();
            
            $table->timestamps();
            
            // Unique Constraint: One row per user × date × model × provider
            $table->unique(['user_id', 'date', 'api_provider', 'model'], 'usage_users_daily_unique');
            
            // Performance Indexes
            $table->index(['user_id', 'date']);
            $table->index(['date', 'model']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_users_daily');
    }
};
