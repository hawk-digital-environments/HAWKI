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
            $table->string('category')->default('general')->comment('Prompt category (general, system, custom, etc.)');
            $table->string('title')->comment('Prompt title/name');
            $table->string('language', 10)->comment('Language code (e.g. de_DE, en_US)');
            $table->text('description')->nullable()->comment('Prompt description');
            $table->longText('content')->comment('Prompt content/text');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->comment('User who created this prompt');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['category', 'language']);
            $table->index('created_by');
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
