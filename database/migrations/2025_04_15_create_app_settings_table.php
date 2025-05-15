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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('source')->nullable();           // Source Config File
            $table->string('group')->default('basic');      // Group categories coresponding to config files: app, authentication, api, ...
            $table->string('type')->default('string');      // Data type: string, boolean, integer, json
            $table->text('description')->nullable();        // can be modified in panel
            $table->boolean('is_private')->default(false);  // If true, won't be exposed to frontend
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
