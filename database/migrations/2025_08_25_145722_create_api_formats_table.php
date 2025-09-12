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
        Schema::create('api_formats', function (Blueprint $table) {
            $table->id();
            $table->string('unique_name')->unique()->comment('Internal identifier for the API format (e.g., "openai-api")');
            $table->string('display_name')->comment('Human-readable name for the API format');
            $table->string('base_url')->nullable()->comment('Base URL for the API (e.g., https://api.openai.com/v1)');
            $table->json('metadata')->nullable()->comment('Additional configuration and metadata for future extensions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_formats');
    }
};
