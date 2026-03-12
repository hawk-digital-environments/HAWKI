<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->foreignId('ai_tool_id')->constrained('ai_tools')->cascadeOnDelete();
            $table->string('type')->nullable();       // e.g. 'mcp', 'function'
            $table->string('source_id')->nullable();  // external reference ID if needed
            $table->timestamps();

            $table->unique(['ai_model_id', 'ai_tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_tools');
    }
};
