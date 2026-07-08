<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_models', function (Blueprint $table) {
            $table->id();
            /* @see WellKnownSystemModelTypes */
            $table->string('model_type');
            $table->string('usage_type');
            $table->string('model_id');
            $table->foreign('model_id')->references('model_id')->on('ai_models')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['model_type', 'usage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_models');
    }
};
