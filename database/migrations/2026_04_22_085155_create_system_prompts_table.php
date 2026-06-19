<?php

use App\Services\Ai\Values\SystemPromptType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('locale');
            /* @see SystemPromptType */
            $table->string('prompt_type');
            $table->string('usage_type');
            $table->text('prompt');
            $table->timestamps();
            $table->unique(['locale', 'prompt_type', 'usage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_prompts');
    }
};
