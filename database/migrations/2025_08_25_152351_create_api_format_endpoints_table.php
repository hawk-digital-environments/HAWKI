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
        Schema::create('api_format_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_format_id')->constrained('api_formats')->onDelete('cascade');
            $table->string('name')->comment('Endpoint identifier (e.g., chat.create, models.list)');
            $table->string('path')->comment('Endpoint path (e.g., /chat/completions)');
            $table->enum('method', ['GET', 'POST', 'PUT', 'DELETE'])->default('POST')->comment('HTTP method');
            $table->boolean('is_active')->default(true)->comment('Whether this endpoint is available');
            $table->json('metadata')->nullable()->comment('Schemas, examples, rate-limit hints, etc.');
            $table->timestamps();

            // Unique constraint on api_format_id + name combination
            $table->unique(['api_format_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_format_endpoints');
    }
};
