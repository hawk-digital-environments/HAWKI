<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(true);
            $table->string('model_id')->unique();
            $table->string('label');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('tools')->nullable();         // model capability flags (stream, file_upload, etc.)
            $table->json('default_params')->nullable();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
