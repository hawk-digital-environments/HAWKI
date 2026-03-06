<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds support for advanced token types and access token tracking:
     * - Access token tracking for HAWKI API usage (personal_access_tokens)
     * - Standard tokens (prompt, completion, total)
     * - Cache tokens (read/creation) for Anthropic/OpenAI/Vertex AI prompt caching
     * - Reasoning tokens for o1/o3-mini/Claude Extended Thinking models
     * - Audio tokens for GPT-4o Audio and similar multimodal models
     * - Server tool use (web search, grounding, etc.) as flexible JSON field
     * - Error tracking for failed requests
     * 
     * Column order matches LiteLLM structure:
     * user_id -> room_id -> type -> access_token_id -> api_provider -> model -> tokens...
     */
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            // Check if columns already exist before adding
            if (!Schema::hasColumn('usage_records', 'access_token_id')) {
                $table->unsignedBigInteger('access_token_id')->nullable()->after('room_id');
            }
            if (!Schema::hasColumn('usage_records', 'total_tokens')) {
                $table->unsignedBigInteger('total_tokens')->default(0)->after('completion_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'cache_read_input_tokens')) {
                $table->unsignedBigInteger('cache_read_input_tokens')->default(0)->after('total_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'cache_creation_input_tokens')) {
                $table->unsignedBigInteger('cache_creation_input_tokens')->default(0)->after('cache_read_input_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'reasoning_tokens')) {
                $table->unsignedBigInteger('reasoning_tokens')->default(0)->after('cache_creation_input_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'audio_input_tokens')) {
                $table->unsignedBigInteger('audio_input_tokens')->default(0)->after('reasoning_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'audio_output_tokens')) {
                $table->unsignedBigInteger('audio_output_tokens')->default(0)->after('audio_input_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'server_tool_use')) {
                $table->json('server_tool_use')->nullable()->after('audio_output_tokens');
            }
            if (!Schema::hasColumn('usage_records', 'is_error')) {
                $table->boolean('is_error')->default(false)->after('server_tool_use');
            }
        });
        
        // Add indexes if they don't exist
        if (!Schema::hasIndex('usage_records', ['is_error'])) {
            Schema::table('usage_records', function (Blueprint $table) {
                $table->index('is_error');
            });
        }
        if (!Schema::hasIndex('usage_records', ['access_token_id'])) {
            Schema::table('usage_records', function (Blueprint $table) {
                $table->index('access_token_id');
            });
        }
        
        // Reorder columns: Move type, api_provider, model after room_id, then access_token_id
        // This is MySQL/MariaDB specific
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `type` VARCHAR(20) NULL AFTER `room_id`');
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `access_token_id` BIGINT UNSIGNED NULL AFTER `type`');
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `api_provider` VARCHAR(255) NULL AFTER `access_token_id`');
            DB::statement('ALTER TABLE usage_records MODIFY COLUMN `model` VARCHAR(255) NULL AFTER `api_provider`');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex(['is_error']);
            $table->dropIndex(['access_token_id']);
            $table->dropColumn([
                'access_token_id',
                'total_tokens',
                'cache_read_input_tokens',
                'cache_creation_input_tokens',
                'reasoning_tokens',
                'audio_input_tokens',
                'audio_output_tokens',
                'server_tool_use',
                'is_error',
            ]);
        });
    }
};
