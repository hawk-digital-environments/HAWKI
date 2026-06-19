<?php

use App\Models\Ai\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_usage_rules', function (Blueprint $table) {
            $table->foreignIdFor(AiModel::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('usage_type')->comment('Defines the type of usage this rule applies to. Search for WellKnownUsageTypes for common values.');
            $table->timestamps();
            $table->index(['ai_model_id', 'usage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_usage_rules');
    }
};
