<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
    * This migration creates the provider_settings table if it doesn't already exist,
    * and ensures that all required columns are present.
     */
    public function up(): void
    {
            Schema::create('provider_settings', function (Blueprint $table) {
                $table->id();
                $table->string('provider_name')->unique();
                $table->string('api_format')->nullable();
                $table->string('api_key')->nullable();
                $table->string('base_url')->nullable();
                $table->string('ping_url')->nullable();
                $table->boolean('is_active')->default(false);
                $table->json('additional_settings')->nullable();
                $table->timestamps();
            });
    


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_settings');

        }
};