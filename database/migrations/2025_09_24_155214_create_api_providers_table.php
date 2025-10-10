<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the new api_providers table (renamed from provider_settings).
     */
    public function up(): void
    {
        // Check if table already exists (due to previous migration attempts)
        if (!Schema::hasTable('api_providers')) {
            Schema::create('api_providers', function (Blueprint $table) {
                $table->id();
                $table->string('provider_name')->unique();
                $table->foreignId('api_format_id')->nullable()->constrained('api_formats')->onDelete('set null');
                $table->text('api_key')->nullable();
                $table->string('base_url')->nullable();
                $table->boolean('is_active')->default(false);
                $table->integer('display_order')->default(0);
                $table->json('additional_settings')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_providers');
    }
};
