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
            $table->json('metadata')->nullable()->comment('Additional configuration and metadata for future extensions');
            $table->string('client_adapter')->nullable()->comment('Client adapter name for mapping to existing AI client classes');
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
