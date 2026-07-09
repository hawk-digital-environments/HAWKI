<?php

use App\Models\Ai\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('locale');
            $table->foreignIdFor(AiModel::class);
            $table->longText('description');
            $table->timestamps();
            $table->unique(['ai_model_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_descriptions');
    }
};
